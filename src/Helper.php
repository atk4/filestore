<?php

declare(strict_types=1);

namespace Atk4\Filestore;

use Atk4\Filestore\Model\File;
use Atk4\Ui\App;

class Helper
{
    /**
     * @return never
     */
    public static function download(File $model, App $app = null): void
    {
        $headers = [
            'Content-Description' => 'File Transfer',
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'must-revalidate',
            'Expires' => '-1',
            'Content-Disposition' => 'attachment; filename="' . $model->get('meta_filename') . '"',
            'Content-Length' => (string) $model->get('meta_size'),
            'Pragma' => 'public',
            'Accept-Ranges' => 'bytes',
        ];

        static::output($model, $headers, $app);
    }

    /**
     * @return never
     */
    protected static function output(File $model, array $headers, App $app = null): void
    {
        $headers = self::normalizeHeaders($headers);

        $location = $model->get('location');

        if ($app !== null) {
            $app->terminate($model->flysystem->read($location), $headers);
        }

        $isCli = \PHP_SAPI === 'cli'; // for phpunit

        foreach ($headers as $k => $v) {
            if (!$isCli) {
                $kCamelCase = preg_replace_callback('~(?<![a-zA-Z])[a-z]~', function ($matches) {
                    return strtoupper($matches[0]);
                }, $k);

                header($kCamelCase . ': ' . $v);
            }
        }

        fpassthru($model->flysystem->readStream($location));

        exit;
    }

    /**
     * @return never
     */
    public static function view(File $model, App $app = null): void
    {
        $headers = [
            'Content-Description' => 'File Transfer',
            'Content-Type' => $model->get('meta_mime_type'),
            'Cache-Control' => 'must-revalidate',
            'Expires' => '-1',
            'Content-Disposition' => 'inline; filename="' . $model->get('meta_filename') . '"',
            'Content-Length' => (string) $model->get('meta_size'),
            'Pragma' => 'public',
            'Accept-Ranges' => 'bytes',
        ];

        static::output($model, $headers, $app);
    }

    // copied from Atk4/Ui/App
    private static function normalizeHeaders(array $headers): array
    {
        $res = [];
        foreach ($headers as $k => $v) {
            if (is_numeric($k) && ($p = strpos($v, ':')) !== false) {
                $k = substr($v, 0, $p);
                $v = substr($v, $p + 1);
            }

            $res[strtolower(trim($k))] = trim($v);
        }

        return $res;
    }
}
