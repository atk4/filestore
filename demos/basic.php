<?php

require '../vendor/autoload.php';

use atk4\data\Model;
use atk4\data\UserAction\Generic;
use atk4\ui\App;
use atk4\ui\jsExpression;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;

$app = new App('Filestore Demo');
$app->initLayout('Centered');

// change this as needed
$app->dbConnect('mysql://atk4_test:atk4_pass@localhost/atk4_test__filestore');

// specify folder where files will be actually stored
$adapter = new Local(__DIR__ . '/localfiles');
$app->filesystem = new Filesystem($adapter);

class Friend extends Model
{
    public $table = 'friend';

    public function init() :void
    {
        parent::init();

        $this->addField('name'); // friend's name
        $this->addField('file', new \atk4\filestore\Field\File($this->app->filesystem)); // storing file here
        $this->addField('file2', new \atk4\filestore\Field\File($this->app->filesystem)); // storing file here
        //$this->hasOne('file_id', new \atk4\filestore\Model\File());
    }
}

\atk4\schema\Migration::of(new Friend($app->db))->run();
\atk4\schema\Migration::of(new \atk4\filestore\Model\File($app->db))->run();

$col = $app->add('Columns');

$form = \atk4\ui\Form::addTo($col->addColumn());
$form->setModel(new Friend($app->db));
$form->model->tryLoad(1);

$gr = \atk4\ui\Grid::addTo($col->addColumn(), ['menu' => false, 'paginator' => false]);
$gr->setModel(new \atk4\filestore\Model\File($app->db));
//$col->js(true, new jsExpression('setInterval(function() { []; }, 2000)', [$gr->jsReload()]));

$form->onSubmit(function($f) use ($gr) {

    $f->model->save();

    return [
        $gr->jsReload()
    ];
});

$app->add(['ui' => 'divider']);

$crud = \atk4\ui\CRUD::addTo($app);
$crud->setModel(new Friend($app->db));

$callback = \atk4\ui\Callback::addTo($app, ['appSticky' => 'true']);
$callback->set(function() use ($crud) {
    $id = $crud->app->stickyGet('row_id');
    $crud->model->load($id)->ref('file')->download();
});

$crud->addActionButton(
    [null,'icon'=>'download'],
    new jsExpression(
        'document.location = "' . $callback->getJSURL().'&row_id="+'.$crud->table->jsRow()->data('id')->jsRender()
    )
);