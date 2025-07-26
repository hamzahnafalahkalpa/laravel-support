<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

trait HasColorFlag
{
  private $__master_colors = [
    "#0A7B94",
    "#A8C2C6",
    "#5BC187",
    "#0C9C84",
    "#7F699B",
    "#67e3e0",
    "#003F54",
    "#FBE870",
    "#FF9F00",
    "#A77D61",
    "#A0C922",
    "#FFFF19",
    "#FF3A30",
    "#FF9792",
    "#4653af",
    "#537728",
    "#0cee2a",
    "#be1706",
    "#4b6c7c",
    "#fc1bc4",
    "#31e9c3",
    "#bb7f2d",
    "#9202d2",
    "#e04271",
    "#6b100d",
    "#9c5c79",
    "#65cded",
    "#985347",
    "#334355",
    "#42d6da",
    "#b6dd35",
    "#d52a5d",
    "#4621ad",
    "#7c5588",
    "#c1c7dd",
    "#60251d",
    "#825baa",
    "#4297f6",
    "#622488",
    "#a1aeb4",
    "#6cc68c",
    "#9555b3",
    "#f4c9ea",
    "#0fabe6",
    "#996452",
    "#6b307d",
    "#9fc967",
    "#0c7c5a",
    "#391174",
    "#122063"
  ];

  /** @var string */
  private $__flag_color;


  public function init(): self
  {
    $this->__master_colors = config('app.colors') ?? $this->__master_colors;
    return $this;
  }

  public function getFlagColor(): string
  {
    $this->init();
    return $this->__flag_color;
  }

  /**
   * Retrieves the color at the specified position from the static $masterColor array.
   *
   * @param int $pos The position of the color in the array.
   * @return mixed The color at the specified position, or null if the position is out of bounds.
   */
  public function getColor($pos)
  {
    $this->init();
    return $this->__master_colors[$pos];
  }

  /**
   * Retrieves a random color from the static $__master_colors array.
   *
   * @return mixed The randomly selected color from the $__master_colors array.
   */
  public function randomColor()
  {
    $this->init();
    return $this->__master_colors[rand(0, count($this->__master_colors) - 1)];
  }
}
