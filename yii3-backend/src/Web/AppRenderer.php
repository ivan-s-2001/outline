<?php

declare(strict_types=1);

namespace App\Web;

use JsonException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

final readonly class AppRenderer
{
    private string $root;

    public function __construct()
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function render(
        ServerRequestInterface $request,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        ?string $rootShareId = null,
    ): ResponseInterface {
        $manifestPath = $this->root . '/public/static/.vite/manifest.json';
        $templatePath = $this->root . '/resources/app.html';

        if (!is_file($manifestPath)) {
            return $this->missingBuildResponse($responseFactory, $streamFactory);
        }
        if (!is_file($templatePath)) {
            throw new RuntimeException('Application HTML template is missing.');
        }

        $manifest = $this->decodeManifest($manifestPath);
        $entry = $this->findEntry($manifest);
        $nonce = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $styles = $this->styleTags($manifest, $entry, $nonce);
        $scripts = sprintf(
            '<script type="module" nonce="%s" src="/static/%s"></script>',
            htmlspecialchars($nonce, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars((string) $entry['file'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        );

        $appUrl = rtrim($this->env('APP_URL', $this->requestOrigin($request)), '/');
        $collaborationUrl = preg_replace('/^http/', 'ws', $appUrl) . '/realtime';
        $environment = [
            'ENVIRONMENT' => 'production',
            'APP_NAME' => 'Outline',
            'URL' => $appUrl,
            'CDN_URL' => null,
            'COLLABORATION_URL' => $collaborationUrl,
            'DEFAULT_LANGUAGE' => 'ru_RU',
            'EMAIL_ENABLED' => true,
            'DROPBOX_APP_KEY' => null,
            'SENTRY_DSN' => null,
            'SENTRY_TUNNEL' => null,
            'GOOGLE_ANALYTICS_ID' => null,
            'VERSION' => 'yii3-mariadb-0.1.0',
            'MAXIMUM_IMPORT_SIZE' => 100 * 1024 * 1024,
            'MAXIMUM_UPLOAD_SIZE' => (int) $this->env('FILE_STORAGE_UPLOAD_MAX_SIZE', '262144000'),
            'SUBDOMAINS_ENABLED' => false,
            'ROOT_SHARE_ID' => $rootShareId,
            'analytics' => [],
        ];

        $html = file_get_contents($templatePath);
        if ($html === false) {
            throw new RuntimeException('Unable to read application HTML template.');
        }

        try {
            $environmentJson = json_encode(
                $environment,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
                | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to serialize frontend environment.', 0, $exception);
        }

        $html = strtr($html, [
            '{{lang}}' => 'ru',
            '{{title}}' => 'Outline',
            '{{styles}}' => $styles,
            '{{scripts}}' => $scripts,
            '{{environment}}' => $environmentJson,
            '{{nonce}}' => htmlspecialchars($nonce, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        ]);

        $response = $responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Expires', '0')
            ->withHeader(
                'Content-Security-Policy',
                "default-src 'self'; "
                . "script-src 'self' 'nonce-{$nonce}'; "
                . "style-src 'self' 'nonce-{$nonce}' 'unsafe-inline'; "
                . "img-src 'self' data: blob: https:; "
                . "font-src 'self' data:; "
                . "connect-src 'self' ws: wss: https:; "
                . "frame-src 'self' https:; "
                . "worker-src 'self' blob:; "
                . "object-src 'none'; base-uri 'self'; frame-ancestors 'self'",
            );

        return $response->withBody($streamFactory->createStream($html));
    }

    /** @return array<string, array<string, mixed>> */
    private function decodeManifest(string $path): array
    {
        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('Unable to read Vite manifest.');
        }

        try {
            $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Vite manifest is invalid.', 0, $exception);
        }

        if (!is_array($manifest)) {
            throw new RuntimeException('Vite manifest has an invalid format.');
        }

        return $manifest;
    }

    /**
     * @param array<string, array<string, mixed>> $manifest
     * @return array<string, mixed>
     */
    private function findEntry(array $manifest): array
    {
        foreach (['app/index.tsx', 'index', 'index.html'] as $key) {
            if (isset($manifest[$key]) && is_array($manifest[$key])) {
                return $manifest[$key];
            }
        }

        foreach ($manifest as $item) {
            if (is_array($item) && ($item['isEntry'] ?? false) === true) {
                return $item;
            }
        }

        throw new RuntimeException('Vite entry point was not found in manifest.');
    }

    /**
     * @param array<string, array<string, mixed>> $manifest
     * @param array<string, mixed> $entry
     */
    private function styleTags(array $manifest, array $entry, string $nonce): string
    {
        $files = [];
        $visited = [];
        $collect = function (array $item) use (&$collect, &$files, &$visited, $manifest): void {
            foreach ((array) ($item['css'] ?? []) as $css) {
                if (is_string($css)) {
                    $files[$css] = true;
                }
            }
            foreach ((array) ($item['imports'] ?? []) as $import) {
                if (!is_string($import) || isset($visited[$import]) || !isset($manifest[$import])) {
                    continue;
                }
                $visited[$import] = true;
                $collect($manifest[$import]);
            }
        };
        $collect($entry);

        $tags = [];
        foreach (array_keys($files) as $file) {
            $tags[] = sprintf(
                '<link rel="stylesheet" nonce="%s" href="/static/%s" />',
                htmlspecialchars($nonce, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                htmlspecialchars($file, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            );
        }

        return implode("\n    ", $tags);
    }

    private function missingBuildResponse(
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
    ): ResponseInterface {
        $html = <<<'HTML'
<!doctype html><html lang="ru"><head><meta charset="utf-8"><title>Outline — сборка не найдена</title></head>
<body><main style="max-width:760px;margin:60px auto;font:16px/1.55 system-ui,sans-serif">
<h1>React-интерфейс ещё не собран</h1>
<p>Запустите в терминале проекта Open Server Panel:</p>
<pre>.osp\bin\build-frontend.cmd</pre>
</main></body></html>
HTML;

        return $responseFactory->createResponse(503)
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withHeader('Cache-Control', 'no-store')
            ->withBody($streamFactory->createStream($html));
    }

    private function requestOrigin(ServerRequestInterface $request): string
    {
        $uri = $request->getUri();
        $scheme = $uri->getScheme() ?: 'https';
        $host = $uri->getHost() ?: 'outline.local';
        $port = $uri->getPort();
        $authority = $host;
        if ($port !== null && !(($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80))) {
            $authority .= ':' . $port;
        }

        return $scheme . '://' . $authority;
    }

    private function env(string $name, string $default): string
    {
        $value = getenv($name);
        if ($value !== false && $value !== '') {
            return (string) $value;
        }

        return isset($_ENV[$name]) && $_ENV[$name] !== '' ? (string) $_ENV[$name] : $default;
    }
}
