<?php

require'../vendor/autoload.php';

use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;

$app = new \atk4\ui\App('Filestore Demo');
$app->initLayout('Centered');
//$app->dbConnect('sqlite::db.sqlite');
$app->dbConnect('mysql://root:root@localhost/atk4');


$adapter = new Local(__DIR__.'/localfiles');
$app->filesystem = new Filesystem($adapter);


class Friend extends \atk4\data\Model {
    public $table = 'friend';

    function init() {
        parent::init();

        $this->addField('name'); // friend's name
        $this->addField('file_id', new \atk4\filestore\Field\File($this->app->filesystem)); // storing file here
    }
}

$col = $app->add('Columns');


$form = $col->addColumn()->add('Form');
$form->setModel(new Friend($app->db));
$form->model->tryLoad(1);

$col->addColumn()->add('CRUD')->setModel(new \atk4\filestore\Model\File($app->db));
