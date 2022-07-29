<?php

namespace App\Collections;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * @template TKey of array-key
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends EloquentCollection<TKey, TModel>
 */
class Collection extends EloquentCollection
{
  //
}
