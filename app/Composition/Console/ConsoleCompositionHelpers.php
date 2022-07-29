<?php

namespace App\Composition\Console;

use Illuminate\Filesystem\Filesystem;

use App\Composition\Exceptions\ConsoleCompositionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Stringable;

trait ConsoleCompositionHelpers
{
  /**
   * Добавляет выражение импорта к файлу модели
   *
   * @param string $content Содержимое файла класса
   * @param iterable $imports Выражения импорта
   */
  protected function addImports(string &$content, iterable $imports): void
  {
    if (!str($content)->contains('namespace'))
      throw new ConsoleCompositionException;

    $existsing_imports = str($content)->matchAll('/use (.*);/');

    $imports = collect($imports)
      ->diff($existsing_imports)
      ->map(fn (string $name): string => "use $name;");

    if ($imports->isNotEmpty())
      $this->insertAfterFirst($content, 'namespace', $imports);
  }

  /**
   * Добавляет аннотацию к классу
   *
   * @param string $content Содержимое файла класса
   * @param Collection $props Свойства аннотации
   */
  protected function appendOrCreateAnnotation(string $content, Collection $props): string
  {
    $contains_annotation = (bool) str($content)->match('/\/\*\*[\s\S]*\*\/[\s\S]class/')->value;

    $annotation = $contains_annotation
      ? $this->appendAnnotation($content, $props)
      : $this->createAnnotation($props);

    if ($contains_annotation) {
      $before = str($content)->before('/**');
      $after  = str($content)->after('*/');
      $content = $before . $annotation . $after;
    } else {
      $this->insertBeforeFirst($content, 'class', [$annotation]);
    }

    return $content;
  }

  /**
   * Создает аннотацию
   *
   * @param Collection $props Свойства аннотации
   */
  protected function createAnnotation(Collection $props): string
  {
    $props_string = $props
      ->map(fn ($property) => " * $property")
      ->implode("\n");

    return "/**\n$props_string\n */";
  }

  /**
   * Добавляет новые свойства к существующей аннотации
   *
   * @param string $content Содержимое файла класса
   * @param Collection $props Свойства аннотации
   */
  protected function appendAnnotation(string $content, Collection $props): string
  {
    $existing_annotation = str($content)
      ->betweenFirst('/**', '*/')
      ->trim("\n");

    $annotation = $this->createAnnotation(
      $existing_annotation
        ->matchAll('/\s?\*\s?(.*)[\s\S]/')
        ->merge($props)
        ->unique()
    );

    return $annotation;
  }

  /**
   * Полное название класса модели
   *
   * @param string $name Имя модели
   */
  protected function qualifyModel(string $name): string
  {
    return $this->qualify($name, $this->getModelsNamespace(...));
  }

  /**
   * Полное название класса колекции
   *
   * @param string $name Имя колекции
   */
  protected function qualifyCollection(string $name): string
  {
    return $this->qualify($name, $this->getCollectionsNamespace(...));
  }

  /**
   * Полное название класса
   *
   * @param string $name Имя класса
   */
  protected function qualify(string $name, callable $namespaceGetter): string
  {
    $name = ltrim($name, '\\/');

    $name = str_replace('/', '\\', $name);

    $rootNamespace = $this->getRootNamespace();

    if (str($name)->startsWith($rootNamespace)) return $name;

    return $this->qualify(
      $namespaceGetter(trim($rootNamespace, '\\')) . '\\' . $name,
      $namespaceGetter
    );
  }


  /**
   * Корневое пространство имен
   */
  protected function getRootNamespace(): string
  {
    return app()->getNamespace();
  }

  /**
   * Пространство имен для моделей
   *
   * @param string $rootNamespace Корневое пространство имен
   */
  protected function getModelsNamespace(string $rootNamespace): string
  {
    return $this->getSpecifiedNamespace($rootNamespace, 'Models');
  }

  /**
   * Пространство имен для коллекций
   *
   * @param string $rootNamespace Корневое пространство имен
   */
  protected function getCollectionsNamespace($rootNamespace)
  {
    return $this->getSpecifiedNamespace($rootNamespace, 'Collections');
  }

  /**
   * Пространство имен для коллекций
   *
   * @param string $rootNamespace Корневое пространство имен
   */
  protected function getQueryBuildersNamespace($rootNamespace)
  {
    return $this->getSpecifiedNamespace($rootNamespace, 'QueryBuilders');
  }

  /**
   * Дополненное пространство имен
   *
   * @param string $rootNamespace Корневое пространство имен
   * @param string $specifiedNamespace Дополненное пространство имен
   */
  protected function getSpecifiedNamespace(string $rootNamespace, string $specifiedNamespace): string
  {
    return is_dir(app_path($specifiedNamespace))
      ? $rootNamespace . '\\' . $specifiedNamespace
      : $rootNamespace;
  }


  /**
   * Содержимое файла модели
   *
   * @param string $name Имя модели
   */
  protected function getModelContent(string $name): string
  {
    return $this->getContent($this->getModelPath($name));
  }

  /**
   * Содержимое файла шаблона
   *
   * @param string $name Относительный путь до шаблона
   */
  protected function getStubContent(string $path): string
  {
    return $this->getContent($this->getStubPath($path));
  }

  /**
   * Содержимое файла
   *
   * @param string $path Путь до файла
   */
  protected function getContent(string $path)
  {
    $fs = new Filesystem;

    if (!$fs->exists($path)) throw new ConsoleCompositionException;

    return $fs->get($path);
  }

  /**
   * Путь до файла модели
   *
   * @param string $name Имя модели
   */
  protected function getModelPath(string $name): string
  {
    return $this->getPathForQualifiedClass($this->qualifyModel($name));
  }

  /**
   * Путь до шаблона коллекции
   *
   * @param string $stub Относительный путь до шаблона
   */
  protected function getStubPath(string $path): string
  {
    $fs = new Filesystem;

    $path = app()->basePath(trim($path, '/'));

    if (!$fs->exists($path)) throw new ConsoleCompositionException;

    return $path;
  }

  /**
   * Путь до файла класса по его полному названию
   *
   * @param string $q_name Полное (`qualified`) имя класса
   */
  protected function getPathForQualifiedClass(string $q_name): string
  {
    return str($q_name)
      ->replaceFirst($this->getRootNamespace(), '')
      ->replace('\\', '/')
      ->prepend(app_path(), '/')
      ->append('.php');
  }

  /**
   * Вставляет строки перед первым вхождением подстроки в строку
   *
   * @param string $heystack Строка, в котрой производится поиск
   * @param string $needle Строка, по которой производится поиск
   * @param array $subjects Строки для вставки
   */
  protected function insertBeforeFirst(string &$heystack, string $needle, iterable $subjects): void
  {
    $this->insertFirst($heystack, $needle, $subjects);
  }

  /**
   * Вставляет строки после первого вхождения подстроки в строку
   *
   * @param string $heystack Строка, в котрой производится поиск
   * @param string $needle Строка, по которой производится поиск
   * @param array $subjects Строки для вставки
   */
  protected function insertAfterFirst(string &$heystack, string $needle, iterable $subjects): void
  {
    $this->insertFirst($heystack, $needle, $subjects, false);
  }

  /**
   * Вставляет строки по первому вхождению подстроки в строку
   *
   * @param string $heystack Строка, в котрой производится поиск
   * @param string $needle Строка, по которой производится поиск
   * @param array $subjects Строки для вставки
   */
  protected function insertFirst(string &$heystack, string $needle, iterable $subjects, bool $before = true): void
  {
    $lines = preg_split("/((\r?\n)|(\r\n?))/", $heystack);

    foreach ($lines as $line) {
      if (str_contains($line, $needle)) {
        $text = collect($subjects)->implode("\n");
        $replacement = $before ? $text . "\n" . $line : "$line\n\n$text";
        $heystack = str($heystack)->replaceFirst($line, $replacement);
        break;
      }
    }
  }
}
