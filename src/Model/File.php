<?php

namespace atk4\filestore\Model;

class File extends \atk4\data\Model {
    public $table = 'filestore_file';

    public $title_field = 'meta_filename';


    function init()
    {
        parent::init();

        $this->addField('token', ['system'=>true]);
        $this->addField('location');
        $this->addField('url');
        $this->addField('storage');
        $this->hasOne('source_file_id', new self());

        $this->addField('status', ['enum'=>['draft','uploaded','thumbok','normalok','ready','linked']]);

        $this->addField('meta_filename');
        $this->addField('meta_extension');
        $this->addField('meta_md5');
        $this->addField('meta_mime_type');
        $this->addField('meta_size', ['type'=>'integer']);
        $this->addField('meta_is_image', ['type'=>'boolean']);
        $this->addField('meta_image_width', ['type'=>'integer']);
        $this->addField('meta_image_height', ['type'=>'integer']);
    }

    public function newFile()
    {
        $this->unload();

        $this['token'] = uniqid('token-');
        $this['location'] = uniqid('file-');
    }
}
