<?php

namespace atk4\filestore\Model;

use atk4\data\Model;
use atk4\data\UserAction\Generic;

class File extends Model
{
    public $table = 'filestore_file';

    public $title_field = 'meta_filename';

    public $flysystem;

    public function init(): void
    {
        parent::init();

        $this->addField('token', ['system'=>true, 'type' => 'string']);
        $this->addField('location');
        $this->addField('url');
        $this->addField('storage');
        $this->hasOne('source_file_id', new self());

        $this->addField('status', ['enum' => ['draft', 'uploaded', 'thumbok', 'normalok', 'ready', 'linked'], 'default' => 'draft']);

        $this->addField('meta_filename');
        $this->addField('meta_extension');
        $this->addField('meta_md5');
        $this->addField('meta_mime_type');
        $this->addField('meta_size', ['type' => 'integer']);
        $this->addField('meta_is_image', ['type' => 'boolean']);
        $this->addField('meta_image_width', ['type' => 'integer']);
        $this->addField('meta_image_height', ['type' => 'integer']);

        $this->onHook('beforeDelete', function ($model) {
            if ($model->flysystem) {
                $model->flysystem->delete($model['location']);
            }
        });
    }

    public function newFile()
    {
        $this->unload();

        $this['token'] = uniqid('token-');
        $this['location'] = uniqid('file-');
    }

    public function download() {

        $stream = $this->flysystem->readStream($this->get('location'));

        if ($this->persistence->app !== null) {
            $contents = stream_get_contents($stream);
            fclose($stream);

            $this->persistence->app->terminate($contents, [
                'Content-Description' => 'File Transfer',
                'Content-Type' => 'application/octet-stream',
                'Cache-Control' => 'must-revalidate',
                'Expires' => '-1',
                'Content-Disposition' => 'attachment; filename="' . $this->get('meta_filename') . '"',
                'Content-Length' => $this->get('meta_size'),
                'Pragma' => 'public',
                'Accept-Ranges' => 'bytes',
            ]);

            return; // not needed
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Cache-Control: must-revalidate');
        header('Expires: -1');
        header('Content-Disposition: attachment; filename="' . $this->get('meta_filename') . '"');
        header('Content-Length: ' . $this->get('meta_size'));
        header('Pragma: public');
        header('Accept-Ranges: bytes');

        fpassthru($stream);
        exit;
    }
}
