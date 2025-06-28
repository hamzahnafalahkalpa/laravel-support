<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

trait HasArray
{
  /**
   * Checks if a given value is an array.
   *
   * @param mixed $value The value to check.
   * @return bool True if the value is an array, false otherwise.
   */
  public function isArray($value): bool
  {
    return is_array($value);
  }

  /**
   * Checks if a given value exists within an array of libraries.
   *
   * @param mixed $value The value to search for in the libraries array.
   * @param array $libs The array of libraries to search in.
   * @return bool True if the value exists in the libraries array, false otherwise.
   */
  public function inArray($value, array $libs): bool
  {
    return in_array($value, $libs);
  }

  /**
   * Returns the input value as an array.
   *
   * @param mixed $value The value to convert to an array.
   * @return array The input value as an array.
   */
  public function mustArray($value): array
  {
    return ($this->isArray($value)) ? $value : [$value];
  }

  /**
   * Reduces an array to a single value using a callback function.
   *
   * @param array $array The array to reduce.
   * @param callable $callback The callback function to use for reduction.
   * @param mixed $initial The initial value to start the reduction with.
   * @return mixed The reduced value.
   */
  public function reduceArray(array $array, callable $callback, $initial = null)
  {
    return array_reduce($array, $callback, $initial);
  }

  /**
   * Filters an array by a given callback function.
   *
   * @param array $array The array to filter.
   * @param callable $callback The callback function to use for filtering.
   * @return array The filtered array.
   */
  public function filterArray(array $array, callable $callback, int $mode = ARRAY_FILTER_USE_BOTH): array
  {
    return array_filter($array, $callback, $mode);
  }

  /**
   * Searches the array for a given value and returns the first corresponding key if successful.
   *
   * @param array $array The array to search in.
   * @param mixed $value The value to search for.
   * @return mixed The key of the first matching value, or false if not found.
   */
  public function searchArray(array $array, $value)
  {
    return array_search($value, $array, true);
  }


  /**
   * Extracts a slice of the array.
   *
   * @param array $array The array to extract from.
   * @param int $offset The offset to start extracting from.
   * @param int|null $length The length of the slice to extract. If not provided, the rest of the array is extracted.
   * @return array The extracted slice.
   */
  public function slice(array $array, int $offset, ?int $length = null): array
  {
    return array_slice($array, $offset, $length, true);
  }

  public function isAssociative(array $array): bool
  {
    if (empty($array)) {
      return false;
    }

    if (function_exists('array_is_list')) {
      return !array_is_list($array);
    }

    return array_keys($array) !== range(0, count($array) - 1);
  }

  /**
   * Checks if all elements in an array pass the test implemented by the provided function.
   *
   * @param array $array The array to test.
   * @param callable $callback The callback function to use for testing.
   * @return bool True if all elements pass the test, otherwise false.
   */
  public function allArray(array $array, callable $callback): bool
  {
    foreach ($array as $item) {
      if (!$callback($item)) return false;
    }
    return true;
  }

  /**
   * Removes the last element from the array and returns it.
   *
   * @param array &$array The array to remove the element from.
   * @return mixed The removed element.
   */
  public function popArray(array &$array)
  {
    return array_pop($array);
  }

  /**
   * Adds one or more elements to the end of the array.
   *
   * @param array &$array The array to add the elements to.
   * @param mixed $value The element(s) to add.
   * @return int The new number of elements in the array.
   */
  public function pushArray(array &$array, $value)
  {
    return array_push($array, $value);
  }

  /**
   * Adds one or more elements to the beginning of the array.
   *
   * @param array &$array The array to add the elements to.
   * @param mixed $value The element(s) to add.
   * @return int The new number of elements in the array.
   */
  public function unshift(array &$array, $value)
  {
    return array_unshift($array, $value);
  }

  /**
   * Removes the first element from the array and returns it.
   *
   * @param array &$array The array to remove the element from.
   * @return mixed The removed element.
   */
  public function arrayShift(array &$array)
  {
    return array_shift($array);
  }

  /**
   * Applies a user-defined function to each element of an array.
   *
   * @param array &$array The array to traverse.
   * @param callable $callback The function to apply to each element.
   * @return void
   */
  public function walk(array &$array, callable $callback)
  {
    array_walk($array, function (&$value, $key) use ($callback) {
      $callback($value, $key);
    });
  }


  /**
   * Checks if the given key or index exists in the array.
   *
   * @param mixed $key The key to check.
   * @param array $array The array to check in.
   * @return bool True if the key exists, false otherwise.
   */
  public function keyExists($key, array $array)
  {
    return array_key_exists($key, $array);
  }

  public function keys(array $array)
  {
    return array_keys($array);
  }

  public function mapArray(callable $callback, $array)
  {
    return array_map($callback, $array);
  }

  public function mergeArray(...$args)
  {
    foreach ($args as &$arg) $arg = $this->mustArray($arg);
    return array_merge(...$args);
  }


  /**
   * Returns an array of values from input array.
   *
   * @param array $array The input array.
   * @return array The array of values.
   */
  public function arrayValues(array $array)
  {
    return array_values($array);
  }

  /**
   * Merges two arrays into one, overwriting any duplicate keys.
   *
   * @param array $array1 The first array.
   * @param array $array2 The second array.
   * @return array The merged array.
   */
  public function recursiveMerge(array $array1, array $array2)
  {
    return array_merge_recursive($array1, $array2);
  }

  /**
   * Merges two arrays into one, removing any duplicate keys.
   *
   * @param array $arr1 The first array.
   * @param array $arr2 The second array.
   * @return array The merged array.
   */
  public function uniqueMerge($arr1, $arr2)
  {
    return array_unique(array_merge($arr1, $arr2));
  }

  /**
   * Sorts an array in ascending order using a user-defined comparison function.
   *
   * @param array $data The array to sort.
   * @param string $param The key to sort by.
   * @return array The sorted array.
   */
  public function usortMatirks($data, $param)
  {
    usort($data, fn($a, $b) => $a[$param] <=> $b[$param]);
    return $data;
  }


  public function intersectKey(...$arrays)
  {
    return array_intersect_key($arrays[0], array_flip($arrays[1]));
  }

  /**
   * Returns an array containing all the values from arr1 that are present in all the other arguments.
   *
   * @param array ...$arrays The arrays to compare.
   * @return array The common values.
   */
  public function intersect(array ...$arrays)
  {
    return array_intersect_key($arrays[0], $arrays[1]);
  }

  /**
   * Computes the difference of arrays, keeping only the values present in the first array but not in the second.
   *
   * @param array $arr1 The first array.
   * @param array $arr2 The second array.
   * @return array The difference of arrays.
   */
  public function diffKey($arr1, $arr2)
  {
    return \array_diff_key($arr1, $arr2);
  }

  /**
   * Computes the difference of arrays, keeping only the values present in the first array but not in the second.
   *
   * @param array $arr1 The first array.
   * @param array $arr2 The second array.
   * @return array The difference of arrays.
   */
  public function diff(array $array, array ...$excludes)
  {
    return array_diff($array, ...$excludes);
  }

  /**
   * Replaces elements from search with replace in subject array.
   *
   * @param array $subject The array to replace elements in.
   * @param array $search The array of elements to replace.
   * @param array $replace The array of elements to replace with.
   * @return array The array with replaced elements.
   */
  public function replace(array $subject, array $search, array $replace)
  {
    return array_replace($subject, $this->combine($search, $replace));
  }

  /**
   * Plucks an array column from the given array.
   *
   * @param array $array The array to pluck from.
   * @param string $column The column to pluck.
   * @return array The plucked array column.
   */
  public function pluckColumn($array, $column)
  {
    return array_column($array, $column);
  }

  /**
   * Combines two arrays into one, using one as keys and the other as values.
   *
   * @param array $keys The array of keys.
   * @param array $values The array of values.
   * @return array The combined array.
   */
  public function combine(array $keys, array $values)
  {
    return array_combine($keys, $values);
  }

  /**
   * Replaces elements from replace in subject array with keys.
   *
   * @param array $subject The array to replace elements in.
   * @param array $replace The array of values to replace with.
   * @return array The array with replaced elements based on keys.
   */
  public function replaceByKey($subject, $replace)
  {
    return array_replace($subject, $replace);
  }
}
