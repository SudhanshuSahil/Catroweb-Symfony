<?php

namespace App\Catrobat\Services;

use App\Catrobat\CatrobatCode\CodeObject;
use App\Catrobat\CatrobatCode\StatementFactory;
use App\Catrobat\Exceptions\Upload\InvalidXmlException;
use App\Catrobat\Exceptions\Upload\MissingXmlException;
use App\Repository\ProgramRepository;
use Doctrine\DBAL\Types\GuidType;
use SimpleXMLElement;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Class ExtractedCatrobatFile.
 */
class ExtractedCatrobatFile
{
  /**
   * @var
   */
  protected $path;

  /**
   * @var
   */
  protected $web_path;

  /**
   * @var
   */
  protected $dir_hash;

  /**
   * @var SimpleXMLElement
   */
  protected $program_xml_properties;

  /**
   * ExtractedCatrobatFile constructor.
   *
   * @param $base_dir
   * @param $base_path
   * @param $dir_hash
   */
  public function __construct($base_dir, $base_path, $dir_hash)
  {
    $this->path = $base_dir;
    $this->dir_hash = $dir_hash;
    $this->web_path = $base_path;

    if (!file_exists($base_dir.'code.xml'))
    {
      throw new MissingXmlException();
    }

    $content = file_get_contents($base_dir.'code.xml');
    if (false === $content)
    {
      throw new InvalidXmlException();
    }
    $content = str_replace('&#x0;', '', $content, $count);
    $this->program_xml_properties = @simplexml_load_string($content);
    if (false === $this->program_xml_properties)
    {
      throw new InvalidXmlException();
    }
  }

  /**
   * @return string
   */
  public function getName()
  {
    return (string) $this->program_xml_properties->header->programName;
  }

  /**
   * @return string
   */
  public function isDebugBuild()
  {
    if (!isset($this->program_xml_properties->header->applicationBuildType))
    {
      return false; // old program do not have this field, + they should be release programs
    }

    return 'debug' === (string) $this->program_xml_properties->header->applicationBuildType;
  }

  /**
   * @return string
   */
  public function getLanguageVersion()
  {
    return (string) $this->program_xml_properties->header->catrobatLanguageVersion;
  }

  /**
   * @return string
   */
  public function getDescription()
  {
    return (string) $this->program_xml_properties->header->description;
  }

  /**
   * @return mixed
   */
  public function getDirHash()
  {
    return $this->dir_hash;
  }

  /**
   * @return array
   */
  public function getTags()
  {
    $tags = (string) $this->program_xml_properties->header->tags;
    if (strlen($tags) > 0)
    {
      return explode(',', (string) $this->program_xml_properties->header->tags);
    }

    return [];
  }

  /**
   * @return array
   */
  public function getContainingImagePaths()
  {
    $finder = new Finder();
    $file_paths = [];

    if ($this->hasScenes())
    {
      $dir_regex = $this->path.'/*/images/';
      $this->createDirectoryInSceneIfNotExist($this->path, $dir_regex, '/images');
      $finder->files()->in($dir_regex);
      foreach ($finder as $file)
      {
        $parts = explode($this->dir_hash.'/', $file->getRealPath());
        $file_paths[] = '/'.$this->web_path.$parts[1];
      }
    }
    else
    {
      $directory = $this->path.'images/';
      $this->createDirectoryIfNotExist($directory);
      $finder->files()->in($directory);
      foreach ($finder as $file)
      {
        $file_paths[] = '/'.$this->web_path.'images/'.$file->getFilename();
      }
    }

    return $file_paths;
  }

  /**
   * @param $filename
   *
   * @return bool
   */
  public function isFileMentionedInXml($filename)
  {
    $xml = file_get_contents($this->path.'code.xml');

    return false !== strpos($xml, $filename);
  }

  /**
   * @return array
   */
  public function getContainingSoundPaths()
  {
    $finder = new Finder();
    $file_paths = [];

    if ($this->hasScenes())
    {
      $dir_regex = $this->path.'/*/sounds/';
      $this->createDirectoryInSceneIfNotExist($this->path, $dir_regex, '/sounds');
      $finder->files()->in($dir_regex);
      foreach ($finder as $file)
      {
        $parts = explode($this->dir_hash.'/', $file->getRealPath());
        $file_paths[] = '/'.$this->web_path.$parts[1];
      }
    }
    else
    {
      $directory = $this->path.'sounds/';
      $this->createDirectoryIfNotExist($directory);
      $finder->files()->in($directory);
      foreach ($finder as $file)
      {
        $file_paths[] = '/'.$this->web_path.'sounds/'.$file->getFilename();
      }
    }

    return $file_paths;
  }

  /**
   * @return array
   */
  public function getContainingStrings()
  {
    $xml = file_get_contents($this->path.'code.xml');
    $matches = [];
    preg_match_all('#>(.*[a-zA-Z].*)<#', $xml, $matches);

    return array_unique($matches[1]);
  }

  /**
   * @return string|null
   */
  public function getScreenshotPath()
  {
    /**
     * @var File
     */
    $screenshot_path = null;
    if (is_file($this->path.'screenshot.png'))
    {
      $screenshot_path = $this->path.'screenshot.png';
    }
    elseif (is_file($this->path.'manual_screenshot.png'))
    {
      $screenshot_path = $this->path.'manual_screenshot.png';
    }
    elseif (is_file($this->path.'automatic_screenshot.png'))
    {
      $screenshot_path = $this->path.'automatic_screenshot.png';
    }
    $finder = new Finder();
    //$finder->in($this->path->getPath())->directories()->name("automatic_screenshot.png")
    if (null === $screenshot_path)
    {
      $fu = $finder->in($this->path)->files()->name('manual_screenshot.png');

      foreach ($fu as $file)
      {
        $screenshot_path = $file->getPathname();
        break;
      }
    }
    if (null === $screenshot_path)
    {
      $fu = $finder->in($this->path)->files()->name('automatic_screenshot.png');

      foreach ($fu as $file)
      {
        $screenshot_path = $file->getPathname();
        break;
      }
    }

    return $screenshot_path;
  }

  /**
   * @return string
   */
  public function getApplicationVersion()
  {
    return (string) $this->program_xml_properties->header->applicationVersion;
  }

  /**
   * @return string
   */
  public function getRemixUrlsString()
  {
    return trim((string) $this->program_xml_properties->header->url);
  }

  /**
   * @return string
   */
  public function getRemixMigrationUrlsString()
  {
    return trim((string) $this->program_xml_properties->header->remixOf);
  }

  /**
   * @return mixed
   */
  public function getPath()
  {
    return $this->path;
  }

  /**
   * @return mixed
   */
  public function getWebPath()
  {
    return $this->web_path;
  }

  /**
   * @return SimpleXMLElement
   */
  public function getProgramXmlProperties()
  {
    return $this->program_xml_properties;
  }

  public function saveProgramXmlProperties()
  {
    $this->program_xml_properties->asXML($this->path.'code.xml');

    $xml_string = file_get_contents($this->path.'code.xml');

    $xml_string = preg_replace('/<receivedMessage>(.*)&lt;-&gt;ANYTHING<\/receivedMessage>/',
      '<receivedMessage>$1&lt;&#x0;-&#x0;&gt;&#x0;ANYTHING&#x0;</receivedMessage>', $xml_string);

    $xml_string = preg_replace('/<receivedMessage>(.*)&lt;-&gt;(.*)<\/receivedMessage>/',
      '<receivedMessage>$1&lt;&#x0;-&#x0;&gt;$2</receivedMessage>', $xml_string);

    if (null != $xml_string)
    {
      file_put_contents($this->path.'code.xml', $xml_string);
    }
  }

  /**
   * based on: http://stackoverflow.com/a/27295688.
   *
   * @param GuidType          $program_id
   * @param bool              $is_initial_version
   * @param ProgramRepository $program_repository
   * @param bool              $migration_mode
   *
   * @return RemixData[]
   */
  public function getRemixesData($program_id, $is_initial_version, $program_repository, $migration_mode = false)
  {
    $remixes_string = $migration_mode ? $this->getRemixMigrationUrlsString() : $this->getRemixUrlsString();
    $state = RemixUrlParsingState::STARTING;
    $extracted_remixes = [];
    $temp = '';

    for ($index = 0; $index < strlen($remixes_string); ++$index)
    {
      $current_character = $remixes_string[$index];

      if (RemixUrlIndicator::PREFIX_INDICATOR == $current_character)
      {
        if (RemixUrlParsingState::STARTING == $state)
        {
          $state = RemixUrlParsingState::BETWEEN;
        }
        else
        {
          if (RemixUrlParsingState::TOKEN == $state)
          {
            $temp = '';
            $state = RemixUrlParsingState::BETWEEN;
          }
        }
      }
      else
      {
        if (RemixUrlIndicator::SUFFIX_INDICATOR == $current_character)
        {
          if (RemixUrlParsingState::TOKEN == $state)
          {
            $extracted_url = trim($temp);
            if (false === strpos($extracted_url, RemixUrlIndicator::SEPARATOR) && strlen($extracted_url) > 0)
            {
              $extracted_remixes[] = new RemixData($extracted_url);
            }
            $temp = '';
            $state = RemixUrlParsingState::BETWEEN;
          }
        }
        else
        {
          $state = RemixUrlParsingState::TOKEN;
          $temp .= $current_character;
        }
      }
    }

    if (0 == count($extracted_remixes) && strlen($remixes_string) > 0 &&
      false === strpos($remixes_string, RemixUrlIndicator::SEPARATOR))
    {
      $extracted_remixes[] = new RemixData($remixes_string);
    }

    $unique_remixes = [];
    foreach ($extracted_remixes as $remix_data)
    {
      /** @var RemixData $remix_data */
      if ('' === $remix_data->getProgramId())
      {
        continue;
      }

      if (!$remix_data->isScratchProgram())
      {
        // projects can't be a remix of them self
        if ($remix_data->getProgramId() === $program_id)
        {
          continue;
        }

        // This id/date back and forth is for the legacy spec tests.
        // Real world scenarios should always be in the date scenario
        $parent_upload_time = $remix_data->getProgramId();
        $child_upload_time = $program_id;

        $parent = null;
        $child = null;

        if (null !== $program_repository)
        {
          $parent = $program_repository->find($remix_data->getProgramId());
          $child = $program_repository->find($program_id);
        }

        if (null !== $parent && null !== $child)
        {
          $parent_upload_time = $parent->getUploadedAt();
          $child_upload_time = $child->getUploadedAt();
        }

        // case initial version: child must be newer than parent
        if ($is_initial_version && $child_upload_time < $parent_upload_time)
        {
          continue;
        }
      }

      $unique_key = $remix_data->getProgramId().'_'.$remix_data->isScratchProgram();
      if (!array_key_exists($unique_key, $unique_remixes))
      {
        $unique_remixes[$unique_key] = $remix_data;
      }
    }

    return array_values($unique_remixes);
  }

  /**
   * @return array
   */
  public function getContainingCodeObjects()
  {
    $objects = [];
    $objectList = $this->getCodeObjects();
    foreach ($objectList as $object)
    {
      $objects = $this->addObjectsToArray($objects, $object->getCodeObjectsRecursively());
    }

    return $objectList + $objects;
  }

  /**
   * @return array
   */
  public function getCodeObjects()
  {
    $objects = [];
    $objectList = $this->program_xml_properties->objectList->children();
    foreach ($objectList as $object)
    {
      $newObject = $this->getObject($object);
      if (null != $newObject)
      {
        $objects[] = $newObject;
      }
    }

    return $objects;
  }

  /**
   * @return bool
   */
  public function hasScenes()
  {
    return 0 != count($this->program_xml_properties->xpath('//scenes'));
  }

  /**
   * @param $objectTree
   *
   * @return CodeObject
   */
  private function getObject($objectTree)
  {
    $factory = new StatementFactory();

    return $factory->createObject($objectTree);
  }

  /**
   * @param $objects
   * @param $objectsToAdd
   *
   * @return array
   */
  private function addObjectsToArray($objects, $objectsToAdd)
  {
    foreach ($objectsToAdd as $object)
    {
      $objects[] = $object;
    }

    return $objects;
  }

  /**
   * @param $base_path
   * @param $dir_regex
   * @param $dir_name
   */
  private function createDirectoryInSceneIfNotExist($base_path, $dir_regex, $dir_name)
  {
    preg_match('@'.$dir_regex.'@', $dir_regex, $scene_names);

    foreach ($scene_names as $scene_name)
    {
      $directory = $base_path + $scene_name + $dir_name;
      if (!file_exists($directory))
      {
        mkdir($directory, 0777, true);
      }
    }
  }

  /**
   * @param $directory
   */
  private function createDirectoryIfNotExist($directory)
  {
    if (!file_exists($directory))
    {
      mkdir($directory, 0777, true);
    }
  }
}
