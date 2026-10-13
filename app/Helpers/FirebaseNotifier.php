<?php

namespace App\Helpers;

/**
 * FirebaseNotifier — espeja un aviso a Firebase Realtime Database para que
 * llegue "de inmediato" a la app (que escucha ese nodo en vivo), sin
 * necesitar el Admin SDK (pesado, requiere una cuenta de servicio). En vez
 * de eso se autentica como el MISMO usuario anónimo que usa la app (Auth
 * anónima ya habilitada en el proyecto Firebase `inventario123-2dec6`) vía
 * la REST API de Identity Platform, y escribe con ese idToken — las reglas
 * de RTDB (`auth != null`) lo aceptan igual que a cualquier cliente.
 *
 * Si Firebase no responde o algo falla, NO debe tumbar el flujo principal
 * (MySQL ya tiene la notificación guardada, la app la ve en su próximo
 * refresh normal aunque no haya llegado "al instante") — por eso todo va
 * en un try/catch silencioso que a lo más deja un log.
 *
 * El hosting compartido (alwaysdata) falla el handshake TLS hacia Google con
 * una tasa alta (gnutls_handshake(): "non-properly terminated", curl errno
 * 35 — confirmado probando en vivo: ronda el 70-80% de las peticiones,
 * sin relación con HTTP/1.1 vs HTTP/2 ni con forzar TLS 1.2, así que es la
 * compilación de cURL/GnuTLS del hosting, no algo corregible desde PHP). Con
 * una tasa de fallo así, un solo reintento no basta: curlConReintento()
 * insiste varias veces antes de darse por vencido — cada intento es barato
 * (falla rápido, sin esperar el timeout completo) y el costo de insistir de
 * más es mucho menor que el de un aviso "inmediato" que nunca llega.
 */
class FirebaseNotifier
{
    private const API_KEY       = 'AIzaSyDVTbGzOrNwvLGAQW_uEwiuaLsonALSSXQ';
    private const DATABASE_URL  = 'https://inventario123-2dec6-default-rtdb.firebaseio.com';
    private const CACHE_ARCHIVO = ROOT_PATH . '/storage/firebase_session.json';

    /** Escribe (merge) un nodo cualquiera de RTDB. $datos debe ser JSON-serializable. */
    public static function escribir(string $ruta, array $datos): bool
    {
        try {
            $idToken = self::obtenerIdToken();
            if (!$idToken) return false;

            $url = self::DATABASE_URL . '/' . ltrim($ruta, '/') . '.json?auth=' . urlencode($idToken);
            [$code, ] = self::curlConReintento($url, 'PUT', json_encode($datos, JSON_UNESCAPED_UNICODE));
            return $code >= 200 && $code < 300;
        } catch (\Throwable $e) {
            error_log('FirebaseNotifier: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Un curl_exec con varios reintentos inmediatos si la conexión falla a
     * nivel de transporte (errno != 0 → nunca llegó a haber respuesta HTTP,
     * así que no hay ambigüedad de reintentar una escritura a medias: nada
     * se aplicó a medias en Firebase si no hubo respuesta). Con ~75% de
     * fallo por intento, 5 intentos dejan la probabilidad de fallo total
     * en ~0.75⁵ ≈ 24%; cada intento falla en milisegundos (no espera el
     * timeout completo), así que el costo de insistir es bajo.
     */
    private static function curlConReintento(string $url, string $metodo, ?string $body): array
    {
        for ($intento = 1; $intento <= 5; $intento++) {
            $ch = curl_init($url);
            $opts = [
                CURLOPT_CUSTOMREQUEST  => $metodo,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
            ];
            if ($body !== null) $opts[CURLOPT_POSTFIELDS] = $body;
            curl_setopt_array($ch, $opts);
            $resp  = curl_exec($ch);
            $errno = curl_errno($ch);
            $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($errno === 0) return [$code, $resp];
            // Fallo de transporte (TLS, DNS, timeout...): nunca hubo
            // respuesta del servidor, un segundo intento es seguro.
        }
        return [0, null];
    }

    /** Aviso de notificación nueva, bajo notificaciones/<usuario_destino_id>/<id>. */
    public static function avisarNotificacion(int $usuarioDestinoId, int $notificacionId, array $datos): void
    {
        self::escribir("notificaciones/{$usuarioDestinoId}/{$notificacionId}", $datos);
    }

    /**
     * Firebase aquí es un buzón EFÍMERO (solo "avisa ya"; MySQL es la fuente
     * de verdad del historial) — al leerla se borra el nodo espejo para que
     * el árbol no crezca sin límite. $notificacionId null = toda la bandeja
     * del usuario (marcar todas como leídas).
     */
    public static function borrarAviso(int $usuarioDestinoId, ?int $notificacionId = null): void
    {
        $ruta = $notificacionId !== null
            ? "notificaciones/{$usuarioDestinoId}/{$notificacionId}"
            : "notificaciones/{$usuarioDestinoId}";
        try {
            $idToken = self::obtenerIdToken();
            if (!$idToken) return;
            $url = self::DATABASE_URL . '/' . $ruta . '.json?auth=' . urlencode($idToken);
            self::curlConReintento($url, 'DELETE', null);
        } catch (\Throwable $e) {
            error_log('FirebaseNotifier::borrarAviso: ' . $e->getMessage());
        }
    }

    /**
     * idToken válido de la cuenta anónima propia del backend — se crea una
     * sola vez y se reusa/refresca indefinidamente (igual que hace la app
     * con la suya, son cuentas anónimas independientes: una por instalación
     * de la app, una para el backend).
     */
    private static function obtenerIdToken(): ?string
    {
        $cache = is_file(self::CACHE_ARCHIVO) ? json_decode(file_get_contents(self::CACHE_ARCHIVO), true) : null;

        if ($cache && ($cache['expira_en'] ?? 0) > time() + 60) {
            return $cache['id_token'];
        }
        if ($cache && !empty($cache['refresh_token'])) {
            $nuevo = self::refrescar($cache['refresh_token']);
            if ($nuevo) return $nuevo['id_token'];
        }
        return self::crearAnonimo();
    }

    private static function crearAnonimo(): ?string
    {
        $resp = self::post(
            'https://identitytoolkit.googleapis.com/v1/accounts:signUp?key=' . self::API_KEY,
            ['returnSecureToken' => true]
        );
        if (!$resp || empty($resp['idToken'])) return null;

        self::guardarCache($resp['idToken'], $resp['refreshToken'], (int) ($resp['expiresIn'] ?? 3600));
        return $resp['idToken'];
    }

    private static function refrescar(string $refreshToken): ?array
    {
        $resp = self::post(
            'https://securetoken.googleapis.com/v1/token?key=' . self::API_KEY,
            ['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]
        );
        if (!$resp || empty($resp['id_token'])) return null;

        self::guardarCache($resp['id_token'], $resp['refresh_token'], (int) ($resp['expires_in'] ?? 3600));
        return ['id_token' => $resp['id_token']];
    }

    private static function guardarCache(string $idToken, string $refreshToken, int $expiresIn): void
    {
        @file_put_contents(self::CACHE_ARCHIVO, json_encode([
            'id_token'      => $idToken,
            'refresh_token' => $refreshToken,
            'expira_en'     => time() + $expiresIn,
        ]));
    }

    private static function post(string $url, array $body): ?array
    {
        [$code, $resp] = self::curlConReintento($url, 'POST', json_encode($body));
        if ($code < 200 || $code >= 300 || !$resp) return null;
        $json = json_decode($resp, true);
        return is_array($json) ? $json : null;
    }
}
