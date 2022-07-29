<?php

namespace App\Composition\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use App\Composition\Console\ConsoleCompositionHelpers;
use Illuminate\Support\Collection;

class CollectionMakeCommand extends GeneratorCommand
{
  use ConsoleCompositionHelpers;

  protected $name = 'make:collection';

  protected $description = 'Create a new Eloquent Collection class';

  protected $type = 'Collection';


  public function handle()
  {
    $model = $this->getModelInput();

    if ($model && !$this->verify()) return false;

    if (parent::handle() === false) return false;

    if ($model) {
      $name    = $this->getNameInput();
      $q_name  = $this->qualifyClass($name);
      $content = $this->getModelContent($model);

      $this->addImports($content, [$q_name]);

      $composition = $this->replaceClass(
        $this->getStubContent('/stubs/collection.composition.stub'),
        $name
      );
      $content = str($content)->replaceLast('}', $composition)->append("}\n")->value;

      $this->files->put($this->getModelPath($model), $content);
    }
  }



  /**
   * Запускаем все необходимые проверки
   */
  protected function verify(): bool
  {
    return $this->verifyModelExistence() && $this->verifyModelComposition();
  }

  /**
   * Проверяем что модель существует
   */
  protected function verifyModelExistence(): bool
  {
    $model = $this->qualifyModel($this->getModelInput());

    if (!$this->files->exists($this->getModelPath($model))) {
      $this->error("Model $model not found!");
      return false;
    }
    return true;
  }

  /**
   * Проверяем что модель не содержит реализации композиции билдера запросов
   */
  protected function verifyModelComposition(): bool
  {
    $content = $this->getModelContent($this->getModelInput());

    if (str($content)->contains('function newCollection')) {
      $model = $this->qualifyModel($this->getModelInput());
      $this->error("Model $model already has composition for Collection!");
      return false;
    }
    return true;
  }



  protected function buildClass($name): string
  {
    $stub = $this->files->get($this->getStub());

    $stub = $this->replaceNamespace($stub, $name)->replaceClass($stub, $name);

    if ($model = $this->getModelInput()) {
      $this->addImports($stub, [$this->qualifyModel($model)]);

      $this->insertBeforeFirst(
        $stub,
        'class',
        [$this->createAnnotation($this->getAnnotationProps($model))]
      );
    }

    return $stub;
  }



  /**
   * Свойства аннотации для коллекции
   *
   * @param string $model Имя модели
   */
  protected function getAnnotationProps(string $model): Collection
  {
    $model = class_basename($model);

    return collect([
      "@method null|$model first()"
    ]);
  }



  protected function getDefaultNamespace($rootNamespace)
  {
    return $this->getCollectionsNamespace($rootNamespace);
  }

  protected function getStub()
  {
    return $this->getStubPath('/stubs/collection.stub');
  }



  protected function getModelInput(): null|string
  {
    $model = $this->option('model');
    return $model ? trim($model) : $model;
  }

  protected function getArguments()
  {
    return [
      ['name', InputArgument::REQUIRED, 'The name of the Collection'],
    ];
  }

  protected function getOptions()
  {
    return [
      ['model', 'm', InputOption::VALUE_REQUIRED, 'Name of the Model for composing'],
    ];
  }
}
