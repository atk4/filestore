<?php

require'../vendor/autoload.php';

use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;

$app = new \atk4\ui\App('Filestore Demo');
$app->cdn['atk'] = '../public';
$app->initLayout('Centered');

// change this as needed
$app->dbConnect('mysql://root:root@localhost/atk4');

// specify folder where files will be actually stored
$adapter = new Local(__DIR__.'/localfiles');
$app->filesystem = new Filesystem($adapter);

class Friend extends \atk4\data\Model {
    public $table = 'friend';

    function init() {
        parent::init();

        $this->addField('name'); // friend's name
        $this->addField('file', new \atk4\filestore\Field\File($this->app->filesystem)); // storing file here
        $this->addField('file2', new \atk4\filestore\Field\File($this->app->filesystem)); // storing file here
        //$this->hasOne('file_id', new \atk4\filestore\Model\File());
    }
}

$col = $app->add('Columns');

$form = $col->addColumn()->add('Form');
$form->setModel(new Friend($app->db));
$form->model->tryLoad(1);

$gr = $col->addColumn()->add(['Grid', 'menu'=>false, 'paginator'=>false]);
$gr->setModel(new \atk4\filestore\Model\File($app->db));
$col->js(true, new \atk4\ui\jsExpression('setInterval(function() { []; }, 2000)', [$gr->jsReload()]));

$app->add(['ui'=>'divider']);

$app->add('CRUD')->setModel(new Friend($app->db));
