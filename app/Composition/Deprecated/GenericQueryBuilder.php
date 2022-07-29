<?php

namespace App\Composition\Deprecated;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder as EloquentQueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 * @template TCollection of \Illuminate\Database\Eloquent\Collection
 */
class GenericQueryBuilder extends EloquentQueryBuilder
{
  /**
   * The model being queried.
   *
   * @var TModel
   */
  protected $model;

  /**
   * @return TModel|static
   */
  public function make(array $attributes = [])
  {
    return $this->newModelInstance($attributes);
  }

  /**
   * @return TModel|static|null
   */
  public function firstWhere($column, $operator = null, $value = null, $boolean = 'and')
  {
    return $this->where(...func_get_args())->first();
  }

  /**
   * @return TCollection
   */
  public function hydrate(array $items)
  {
    $instance = $this->newModelInstance();

    return $instance->newCollection(array_map(function ($item) use ($items, $instance) {
      $model = $instance->newFromBuilder($item);

      if (count($items) > 1) {
        $model->preventsLazyLoading = Model::preventsLazyLoading();
      }

      return $model;
    }, $items));
  }

  /**
   * @return TCollection
   */
  public function fromQuery($query, $bindings = [])
  {
    return $this->hydrate(
      $this->query->getConnection()->select($query, $bindings)
    );
  }

  /**
   * @return TModel|TCollection|static[]|static|null
   */
  public function find($id, $columns = ['*'])
  {
    if (is_array($id) || $id instanceof Arrayable) {
      return $this->findMany($id, $columns);
    }

    return $this->whereKey($id)->first($columns);
  }

  /**
   * @return TCollection
   */
  public function findMany($ids, $columns = ['*'])
  {
    $ids = $ids instanceof Arrayable ? $ids->toArray() : $ids;

    if (empty($ids)) {
      return $this->model->newCollection();
    }

    return $this->whereKey($ids)->get($columns);
  }

  /**
   * @return TModel|TCollection|static|static[]
   * @throws \Illuminate\Database\Eloquent\ModelNotFoundException<TModel>
   */
  public function findOrFail($id, $columns = ['*'])
  {
    $result = $this->find($id, $columns);

    $id = $id instanceof Arrayable ? $id->toArray() : $id;

    if (is_array($id)) {
      if (count($result) !== count(array_unique($id))) {
        throw (new ModelNotFoundException)->setModel(
          get_class($this->model),
          array_diff($id, $result->modelKeys())
        );
      }

      return $result;
    }

    if (is_null($result)) {
      throw (new ModelNotFoundException)->setModel(
        get_class($this->model),
        $id
      );
    }

    return $result;
  }

  /**
   * @return TModel|static
   */
  public function findOrNew($id, $columns = ['*'])
  {
    if (!is_null($model = $this->find($id, $columns))) {
      return $model;
    }

    return $this->newModelInstance();
  }

  /**
   * @return TModel|TCollection|static[]|static|mixed
   */
  public function findOr($id, $columns = ['*'], Closure $callback = null)
  {
    if ($columns instanceof Closure) {
      $callback = $columns;

      $columns = ['*'];
    }

    if (!is_null($model = $this->find($id, $columns))) {
      return $model;
    }

    return $callback();
  }

  /**
   * @return TModel|static
   */
  public function firstOrNew(array $attributes = [], array $values = [])
  {
    if (!is_null($instance = $this->where($attributes)->first())) {
      return $instance;
    }

    return $this->newModelInstance(array_merge($attributes, $values));
  }

  /**
   * @return TModel|static
   */
  public function firstOrCreate(array $attributes = [], array $values = [])
  {
    if (!is_null($instance = $this->where($attributes)->first())) {
      return $instance;
    }

    return tap($this->newModelInstance(array_merge($attributes, $values)), function ($instance) {
      $instance->save();
    });
  }

  /**
   * @return TModel|static
   */
  public function updateOrCreate(array $attributes, array $values = [])
  {
    return tap($this->firstOrNew($attributes), function ($instance) use ($values) {
      $instance->fill($values)->save();
    });
  }

  /**
   * @return TModel|static
   * @throws \Illuminate\Database\Eloquent\ModelNotFoundException<TModel>
   */
  public function firstOrFail($columns = ['*'])
  {
    if (!is_null($model = $this->first($columns))) {
      return $model;
    }

    throw (new ModelNotFoundException)->setModel(get_class($this->model));
  }

  /**
   * @return TModel|static|mixed
   */
  public function firstOr($columns = ['*'], Closure $callback = null)
  {
    if ($columns instanceof Closure) {
      $callback = $columns;

      $columns = ['*'];
    }

    if (!is_null($model = $this->first($columns))) {
      return $model;
    }

    return $callback();
  }

  /**
   * @return TModel
   * @throws \Illuminate\Database\Eloquent\ModelNotFoundException<TModel>
   */
  public function sole($columns = ['*'])
  {
    try {
      return $this->baseSole($columns);
    } catch (RecordsNotFoundException $exception) {
      throw (new ModelNotFoundException)->setModel(get_class($this->model));
    }
  }

  /**
   * @throws \Illuminate\Database\Eloquent\ModelNotFoundException<TModel>
   */
  public function soleValue($column)
  {
    return $this->sole([$column])->{Str::afterLast($column, '.')};
  }

  /**
   * @throws \Illuminate\Database\Eloquent\ModelNotFoundException<TModel>
   */
  public function valueOrFail($column)
  {
    return $this->firstOrFail([$column])->{Str::afterLast($column, '.')};
  }

  /**
   * @return TCollection|static[]
   */
  public function get($columns = ['*'])
  {
    $builder = $this->applyScopes();

    // If we actually found models we will also eager load any relationships that
    // have been specified as needing to be eager loaded, which will solve the
    // n+1 query issue for the developers to avoid running a lot of queries.
    if (count($models = $builder->getModels($columns)) > 0) {
      $models = $builder->eagerLoadRelations($models);
    }

    return $builder->getModel()->newCollection($models);
  }

  /**
   * @return TModel[]|static[]
   */
  public function getModels($columns = ['*'])
  {
    return $this->model->hydrate(
      $this->query->get($columns)->all()
    )->all();
  }

  /**
   * @return TModel|$this
   */
  public function create(array $attributes = [])
  {
    return tap($this->newModelInstance($attributes), function ($instance) {
      $instance->save();
    });
  }

  /**
   * @return TModel|$this
   */
  public function forceCreate(array $attributes)
  {
    return $this->model->unguarded(function () use ($attributes) {
      return $this->newModelInstance()->create($attributes);
    });
  }

  /**
   * @return TModel|static
   */
  public function newModelInstance($attributes = [])
  {
    return $this->model->newInstance($attributes)->setConnection(
      $this->query->getConnection()->getName()
    );
  }

  /**
   * @return TModel|static
   */
  public function getModel()
  {
    return $this->model;
  }

  /**
   * @param  TModel  $model
   */
  public function setModel(Model $model)
  {
    $this->model = $model;

    $this->query->from($model->getTable());

    return $this;
  }
}
