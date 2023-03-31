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

    /**
     * @param array<string, string> $headers
     *
     * @return never
     */
    protected static function output(File $model, array $headers, App $app = null): void
    {
        // TODO move to App and add support for streams

        $location = $model->get('location');

        if ($app !== null) {
            foreach ($headers as $name => $value) {
                $app->setResponseHeader($name, $value);
            }
            $app->terminate($model->flysystem->read($location));
        }

        $isCli = \PHP_SAPI === 'cli'; // for phpunit

        $headers = self::normalizeHeaders($headers);
        foreach ($headers as $name => $value) {
            if (!$isCli) {
                $name = preg_replace_callback('~(?<![a-zA-Z])[a-z]~', function ($matches) {
                    return strtoupper($matches[0]);
                }, strtolower($name));

                header($name . ': ' . $value);
            }
        }

        fpassthru($model->flysystem->readStream($location));

        exit;
    }

    /**
     * Copied from atk4/ui App class. Used only if App is not available.
     *
     * @param array<string, string> $headers
     *
     * @return array<string, string>
     */
    private static function normalizeHeaders(array $headers): array
    {
        $res = [];
        foreach ($headers as $k => $v) {
            $res[strtolower(trim($k))] = trim($v);
        }

        return $res;
    }
}
