<?php

namespace App\Utils;

use App\ChannelLink;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * PosInnova online channels: the only place that talks to E-Ride (contract v1).
 * POS always calls out; E-Ride never calls in. Bearer key per linked location.
 */
class ChannelClient
{
    /** @var ChannelLink */
    private $link;

    /** Seconds; set while a person waits (a sale being saved), so a dead line costs little. */
    public $cap = null;

    public function __construct(ChannelLink $link)
    {
        $this->link = $link;
    }

    /** https only, except a local test server. Returns the cleaned base URL or null. */
    public static function cleanBaseUrl($url)
    {
        $url = rtrim(trim((string) $url), '/');
        $p = parse_url($url);
        if (empty($p['scheme']) || empty($p['host'])) {
            return null;
        }
        $local = in_array(strtolower($p['host']), ['127.0.0.1', 'localhost'], true);
        if ($p['scheme'] !== 'https' && ! ($local && $p['scheme'] === 'http')) {
            return null;
        }

        return $url;
    }

    public function get($path, array $query = [], $timeout = 15)
    {
        return $this->send('GET', $path, $query, $timeout);
    }

    public function post($path, array $body = [], $idem = null, $timeout = 30)
    {
        return $this->send('POST', $path, $body, $timeout, $idem);
    }

    /** Multipart upload of one image (POST /catalog/images). */
    public function upload($path, array $fields, $file_path, $idem = null)
    {
        $req = $this->request(60, $idem ?: (string) Str::uuid())
            ->attach('file', file_get_contents($file_path), basename($file_path));
        try {
            $res = $req->post($this->url($path), $fields);
        } catch (\Throwable $e) {
            throw new ChannelException('network', $this->short($e->getMessage()), 0);
        }

        return $this->answer($res);
    }

    private function send($method, $path, array $data, $timeout, $idem = null)
    {
        $req = $this->request($timeout, $method === 'POST' ? ($idem ?: (string) Str::uuid()) : null);
        try {
            $res = $method === 'GET' ? $req->get($this->url($path), $data) : $req->asJson()->post($this->url($path), (object) $data);
        } catch (\Throwable $e) {
            throw new ChannelException('network', $this->short($e->getMessage()), 0);
        }

        return $this->answer($res);
    }

    private function request($timeout, $idem = null)
    {
        $headers = ['X-POS-Version' => 'posinnova/'.config('author.app_version', '1')];
        if ($idem) {
            $headers['Idempotency-Key'] = $idem;
        }

        return Http::withToken((string) $this->link->api_key)
            ->withHeaders($headers)
            ->acceptJson()
            ->connectTimeout($this->cap ? min(4, $this->cap) : 8)
            ->timeout($this->cap ? min($timeout, $this->cap) : $timeout);
    }

    private function url($path)
    {
        return rtrim($this->link->base_url, '/').'/'.ltrim($path, '/');
    }

    private function answer($res)
    {
        $status = $res->status();
        $json = $res->json();
        if ($status >= 200 && $status < 300) {
            return is_array($json) ? $json : [];
        }
        $code = is_array($json) ? data_get($json, 'error.code', 'http_'.$status) : 'http_'.$status;
        $msg = is_array($json) ? (string) data_get($json, 'error.message', '') : '';
        $e = new ChannelException($code, $msg !== '' ? $msg : $this->short((string) $res->body()), $status);
        $e->retryAfter = (int) ($res->header('Retry-After') ?: 0);
        $e->data = is_array($json) ? $json : [];

        throw $e;
    }

    private function short($s)
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $s))), 300);
    }
}
