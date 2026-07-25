<?php
namespace App\Core;

class Request
{
    private array $body;
    private array $query;
    private array $files;

    public function __construct()
    {
        // Inicializa sempre — evita "uninitialized property" no PHP 8.2
        $this->body  = [];
        $this->query = $_GET  ?? [];
        $this->files = $_FILES ?? [];

        $raw = (string) file_get_contents('php://input');

        // JSON body
        if ($raw !== '' && str_contains($raw, '{')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $this->body = $decoded;
            }
        }

        // Fallback: $_POST (form-urlencoded)
        if (empty($this->body) && !empty($_POST)) {
            $this->body = $_POST;
        }

        // Fallback: parse_str
        if (empty($this->body) && $raw !== '') {
            $ct = $_SERVER['CONTENT_TYPE'] ?? '';
            if (str_contains($ct, 'application/x-www-form-urlencoded')) {
                parse_str($raw, $parsed);
                $this->body = is_array($parsed) ? $parsed : [];
            }
        }
    }

    public function method(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $override = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']
            ?? $_SERVER['HTTP_X_METHOD_OVERRIDE']
            ?? '';
        if ($override) $method = $override;

        return strtoupper($method);
    }

    public function body(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) return $this->body;
        return $this->body[$key] ?? $default;
    }

    public function query(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) return $this->query;
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = ''): string
    {
        $v = $this->body[$key] ?? $this->query[$key] ?? $default;
        if (in_array($key, ['password', 'current_password', 'new_password'], true)) {
            return (string) $v;
        }
        return trim((string) $v);
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) ($this->body[$key] ?? $this->query[$key] ?? $default);
    }

    /**
     * Retorna o arquivo enviado ou null.
     * Se $errorOut for passado, preenche com mensagem descritiva em caso de falha.
     */
    public function file(string $key, ?string &$errorOut = null): ?array
    {
        // post_max_size excedido → $_FILES fica completamente vazio
        if (
            empty($this->files) &&
            isset($_SERVER['CONTENT_LENGTH']) &&
            (int)$_SERVER['CONTENT_LENGTH'] > 0
        ) {
            $maxPost = $this->parseBytes(ini_get('post_max_size') ?: '8M');
            $errorOut = "Arquivo excede o limite do servidor (post_max_size = "
                      . $this->formatBytes($maxPost) . "). "
                      . "Ajuste post_max_size e upload_max_filesize no PHP.";
            return null;
        }

        $f = $this->files[$key] ?? null;
        if (!$f) {
            $errorOut = 'Nenhum arquivo enviado';
            return null;
        }

        if ($f['error'] === UPLOAD_ERR_OK) {
            return $f;
        }

        $errorOut = match ($f['error']) {
            UPLOAD_ERR_INI_SIZE   => 'Arquivo excede upload_max_filesize do PHP ('
                                   . ini_get('upload_max_filesize') . '). Ajuste a configuração do servidor.',
            UPLOAD_ERR_FORM_SIZE  => 'Arquivo excede o limite definido no formulário.',
            UPLOAD_ERR_PARTIAL    => 'Upload interrompido — o arquivo foi enviado parcialmente. Tente novamente.',
            UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo foi selecionado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Erro interno: diretório temporário não encontrado no servidor.',
            UPLOAD_ERR_CANT_WRITE => 'Erro interno: falha ao gravar no disco.',
            UPLOAD_ERR_EXTENSION  => 'Upload bloqueado por extensão do PHP.',
            default               => 'Erro desconhecido no upload (código ' . $f['error'] . ').',
        };
        return null;
    }

    private function parseBytes(string $val): int
    {
        $val  = trim($val);
        $last = strtolower(substr($val, -1));
        $num  = (int) $val;
        return match ($last) {
            'g' => $num * 1073741824,
            'm' => $num * 1048576,
            'k' => $num * 1024,
            default => $num,
        };
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024) . ' KB';
        return $bytes . ' B';
    }

    public function bearerToken(): string
    {
        $h = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';
        if (!$h && function_exists('apache_request_headers')) {
            $ah = apache_request_headers();
            $h  = $ah['Authorization'] ?? $ah['authorization'] ?? '';
        }
        if (preg_match('/^Bearer\s+(.+)$/i', $h, $m)) return $m[1];
        return $this->query('_token', '');
    }
}
