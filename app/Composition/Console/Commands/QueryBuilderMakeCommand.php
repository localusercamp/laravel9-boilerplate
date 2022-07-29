<?php

namespace App\Composition\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use App\Composition\Console\ConsoleCompositionHelpers;
use Illuminate\Support\Collection;

class QueryBuilderMakeCommand extends GeneratorCommand
{
  use ConsoleCompositionHelpers;

  protected $name = 'make:query-builder';

  protected $description = 'Create a new Eloquent Query Builder class';

  protected $type = 'Query Builder';



  public function handle()
  {
    $model = $this->getModelInput();
    $collection = $this->getCollectionInput();

    if ($model && !$this->verify()) return false;

    if ($collection && !$this->verifyCollectionExistence()) return false;

    if (parent::handle() === false) return false;

    if ($model) {
      $name    = $this->getNameInput();
      $q_name  = $this->qualifyClass($name);
      $content = $this->getModelContent($model);

      $this->addImports($content, [$q_name]);

      $composition = $this->replaceClass(
        $this->getStubContent('/stubs/query-builder.composition.stub'),
        $name
      );
      $content = str($content)->replaceLast('}', $composition)->append("}\n")->value;

      $content = $this->appendOrCreateAnnotation($content, $this->getModelAnnotationProps($name));

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
   * Проверяем что коллекция существует
   */
  protected function verifyCollectionExistence(): bool
  {
    $collection = $this->qualifyCollection($this->getCollectionInput());
    $path = $this->getPathForQualifiedClass($collection);

    if (!$this->files->exists($path)) {
      $this->error("Model $collection not found!");
      return false;
    }
    return true;
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

    if (str($content)->contains('function newEloquentBuilder')) {
      $model = $this->qualifyModel($this->getModelInput());
      $this->error("Model $model already has composition for Query Builder!");
      return false;
    }
    return true;
  }



  protected function buildClass($name): string
  {
    $stub = $this->files->get($this->getStub());

    $stub = $this->replaceNamespace($stub, $name)->replaceClass($stub, $name);

    if ($model = $this->getModelInput()) {
      $imports = [$this->qualifyModel($model)];

      if ($collection = $this->getCollectionInput())
        $imports[] = $this->qualifyCollection($collection);

      $this->addImports($stub, $imports);

      $this->insertBeforeFirst(
        $stub,
        'class',
        [$this->createAnnotation($this->getAnnotationProps($model, $collection))]
      );
    }

    return $stub;
  }



  /**
   * Свойства аннотации для билдера запросов
   *
   * @param string $model Имя модели
   */
  protected function getAnnotationProps(string $model, null|string $collection = null): Collection
  {
    $model = class_basename($model);
    $collection = class_basename($collection);
    return collect()
      ->push("@method null|$model first()")
      ->when($collection, fn (Collection $c) => $c->push("@method $collection get()"));
  }

  /**
   * Свойства аннотации для модели
   *
   * @param string $queryBuilder Имя билдера запросов
   */
  protected function getModelAnnotationProps(string $queryBuilder): Collection
  {
    $basename = class_basename($queryBuilder);

    return collect([
      "@method static $basename query()",
    ]);
  }



  protected function getDefaultNamespace($rootNamespace)
  {
    return $this->getQueryBuildersNamespace($rootNamespace);
  }

  protected function getStub()
  {
    return $this->getStubPath('/stubs/query-builder.stub');
  }



  protected function getModelInput(): null|string
  {
    $model = $this->option('model');
    return $model ? trim($model) : $model;
  }

  protected function getCollectionInput(): null|string
  {
    $collection = $this->option('collection');
    return $collection ? trim($collection) : $collection;
  }

  protected function getArguments()
  {
    return [
      ['name', InputArgument::REQUIRED, 'The name of the Query Builder'],
    ];
  }

  protected function getOptions()
  {
    return [
      ['model', 'm', InputOption::VALUE_REQUIRED, 'Name of the Model for composing'],
      ['collection', 'c', InputOption::VALUE_REQUIRED, 'Name of the Collection for composing'],
    ];
  }
}
