<?php

declare(strict_types=1);

require '../vendor/autoload.php';

use Atk4\Filestore\Field\File;
use Atk4\Filestore\Helper;
use Atk4\Ui\Callback;
use Atk4\Ui\Columns;
use Atk4\Ui\Form;
use Atk4\Ui\JsExpression;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;

class Friend extends \Atk4\Data\Model
{
    public $table = 'friend';

    public $filesystem;

    protected function init(): void
    {
        parent::init();

        $this->addField('name');                                                     // friend's name
        $this->addField('file', new File($this->filesystem));  // storing file here
        $this->addField('file2', new File($this->filesystem)); // storing file here
    }
}

// specify folder where files will be actually stored
$adapter = new \League\Flysystem\Local\LocalFilesystemAdapter(__DIR__ . '/localfiles');
$filesystem = new Filesystem($adapter);

// init App
$app = new \Atk4\Ui\App('Filestore Demo');
$app->initLayout([\Atk4\Ui\Layout\Centered::class]);

// change this as needed
$app->db = Atk4\Data\Persistence::connect('mysql://root:root@localhost/atk4_filestore');

// specify folder where files will be actually stored
$adapter = new Local(__DIR__ . '/localfiles');
$filesystem = new Filesystem($adapter);

class Friend extends \Atk4\Data\Model
{
    public $table = 'friend';

    public $filesystem;

    protected function init(): void
    {
        parent::init();

        $this->addField('name');                                                     // friend's name
        $this->addField('file', new File($this->filesystem));  // storing file here
        $this->addField('file2', new File($this->filesystem)); // storing file here
    }
}

$col = Columns::addTo($app);

$form = Form::addTo($col->addColumn());
$form->setModel(
    new Friend($app->db, [
        'filesystem' => $filesystem,
    ])
);
$form->model->tryLoad(1);

$gr = \Atk4\Ui\Grid::addTo($col->addColumn(), [
    'menu' => false,
    'paginator' => false,
]);
$gr->setModel(new \Atk4\Filestore\Model\File($app->db));

$form->onSubmit(function (Atk4\Ui\Form $form) use ($gr) {
    $form->model->save();

    return [
        $gr->jsReload(),
    ];
});

\Atk4\Ui\View::addTo($app, ['ui' => 'divider']);

$crud = \Atk4\Ui\Crud::addTo($app);
$crud->setModel(new Friend($app->db, ['filesystem' => $filesystem]));

\Atk4\Ui\View::addTo($app, ['ui' => 'divider']);

$callback_download = Callback::addTo($app);
$callback_download->set(function () use ($crud) {
    $id = $crud->getApp()->stickyGet('row_id');
    $model = (clone $crud->model);
    $model_file = $model->load($id)->ref('file');
    Helper::download($model_file, $crud->getApp());
});

$crud->addActionButton(
    ['icon' => 'download'],
    new JsExpression(
        'document.location = "' . $callback_download->getJSURL() . '&row_id="+[]',
        [$crud->table->jsRow()->data('id')]
    )
);

$callback_view = Callback::addTo($app);
$callback_view->set(function () use ($crud) {
    $id = $crud->getApp()->stickyGet('row_id');
    $model = (clone $crud->model);
    $model_file = $model->load($id)->ref('file');
    Helper::view($model_file, $crud->getApp());
});

$crud->addActionButton(
    ['icon' => 'image'],
    new JsExpression(
        'document.location = "' . $callback_view->getJSURL() . '&row_id="+[]',
        [$crud->table->jsRow()->data('id')]
    )
);
