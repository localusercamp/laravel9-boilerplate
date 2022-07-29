<?php

namespace App\Composition\Console\Commands;

use Illuminate\Foundation\Console\ModelMakeCommand as BaseModelMakeCommand;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Support\Str;

class ModelMakeCommand extends BaseModelMakeCommand
{
  public function handle()
  {
    if (parent::handle() === false) return false;

    if ($this->option('without-composition')) return;

    $this->createCollection();
    $this->createQueryBuilder();
  }



  protected function createCollection()
  {
    $collection = Str::studly(class_basename($this->argument('name')));

    $this->call('make:collection', [
      'name' => "{$collection}Collection",
      '-m' => $this->argument('name')
    ]);
  }

  protected function createQueryBuilder()
  {
    $queryBuilder = Str::studly(class_basename($this->argument('name')));
    $collection = Str::studly(class_basename($this->argument('name')));

    $this->call('make:query-builder', [
      'name' => "{$queryBuilder}QueryBuilder",
      '-m' => $this->argument('name'),
      '-c' => "{$collection}Collection",
    ]);
  }

  protected function getOptions()
  {
    $base_options = parent::getOptions();

    $compose_options = [
      ['without-composition', null, InputOption::VALUE_NONE, 'Indicates if the generated model should not have composition classes'],
    ];

    return array_merge($base_options, $compose_options);
  }
}
