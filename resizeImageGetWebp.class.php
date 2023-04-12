<?php
// version 1.1

// Пример:
// $newFileWebp = SBFile::ResizeImageGetWebp($file, array("width" => 200, "height" => 200), BX_RESIZE_IMAGE_EXACT, true);

class SBFile extends CFile
{

  private static function LTrimServerName($url)
  {
    if (mb_strpos($url, $_SERVER['DOCUMENT_ROOT']) === 0)
    {
      $url = '/' . ltrim(mb_substr($url, mb_strlen($_SERVER['DOCUMENT_ROOT'])), '/');
    }
    return $url;
  }

  private static function CreateFileArray($url, $title = '', $alt = '')
  {
    $url = self::LTrimServerName($url);
    $imgArr = self::MakeFileArray($url);

    if (is_numeric($url))
    {
      $url = self::LTrimServerName($imgArr['tmp_name']);
    }

    $srcImageInfo = getimagesize($_SERVER['DOCUMENT_ROOT'] . $url);
    $srcImageInfo['width'] = $srcImageInfo[0];
    $srcImageInfo['height'] = $srcImageInfo[1];

    $image = array(
      'SRC' => self::LTrimServerName($imgArr['tmp_name']),
      'FILE_NAME' => pathinfo($imgArr['tmp_name'], PATHINFO_BASENAME),
      'ORIGINAL_NAME' => $imgArr['name'],
      'SUBDIR' => dirname(self::LTrimServerName($imgArr['tmp_name'])),
      'WIDTH' => $srcImageInfo['width'],
      'HEIGHT' => $srcImageInfo['height'],
      'CONTENT_TYPE' => $imgArr['type']
    );
    if ($title) $image['TITLE'] = $title;
    if ($alt) $image['ALT'] = $alt;

    $uploadDirName = COption::GetOptionString("main", "upload_dir", "upload");

    if (mb_strpos($image["SUBDIR"], '/' . $uploadDirName . '/') === 0)
    {
      $image["SUBDIR"] = ltrim(mb_substr($image["SUBDIR"], mb_strlen('/' . $uploadDirName . '/')), '/');
    }

    return $image;
  }

  private static function IsAnimated($filename)
  {
    // https://www.php.net/manual/ru/function.imagecreatefromgif.php#104473
    if (!($fh = @fopen($filename, 'rb')))
      return false;
    $count = 0;

    while (!feof($fh) && $count < 2)
    {
      $chunk = fread($fh, 1024 * 100); //read 100kb at a time
      $count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches);
    }

    fclose($fh);
    return $count > 1;
  }

  private static function ConvertImageWebp($src, $quality = -1)
  {
    // Если адрес полный - превращаем в относительно корня сайта
    $src = self::LTrimServerName($src);

    $fileInfo = pathinfo($src);
    $output_file = $fileInfo['dirname'] . '/' . $fileInfo['filename'] . '.webp';

    $file = $_SERVER['DOCUMENT_ROOT'] . $src;
    $file_type = image_type_to_mime_type(exif_imagetype($file));

    switch ($file_type)
    {
      case 'image/jpeg':
        $image = @ImageCreateFromJpeg($file);
        if (!$image)
        {
          $image = imagecreatefromstring(file_get_contents($file));
        }
        break;

      case 'image/png':
        $image = imagecreatefrompng($file);
        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);
        break;

      case 'image/gif':
        if (!self::IsAnimated($file))
        {
          $image = @imagecreatefromgif($file);
        } else
        {
          return false;
        }
        break;

      case 'image/bmp':
        $image = imagecreatefrombmp($file);
        break;

      default:
        return false;
    }

    if (!$image)
    {
      imagedestroy($image);
      return false;
    }

    $result = imagewebp($image, $_SERVER['DOCUMENT_ROOT'] . $output_file, $quality);

    // Free up memory
    imagedestroy($image);

    if ($result === false) return false;

    if (filesize($_SERVER['DOCUMENT_ROOT'] . $output_file) > 0)
    {
      return $output_file;
    } else
    {
      unlink($_SERVER['DOCUMENT_ROOT'] . $output_file);
      return false;
    }

  }

  public static function ResizeImageGetWebp(
    $file,
    $arSize = array(),
    $resizeType = 'BX_RESIZE_IMAGE_PROPORTIONAL',
    $bInitSizes = false,
    $arFilters = false,
    $bImmediate = false,
    $jpgQuality = false)
  {

    if (!is_array($file) && (intval($file) > 0 || is_string($file)))
    {
      $file = self::CreateFileArray($file);
    }

    if (!is_array($file) || !array_key_exists("FILE_NAME", $file) || $file["FILE_NAME"] == '')
      return false;

    // Если это анимированный gif — прерываем функцию и отдаём файл
    $filesrc = $_SERVER['DOCUMENT_ROOT'] . self::LTrimServerName($file["SRC"]);
    $file_type = image_type_to_mime_type(exif_imagetype($filesrc));

    if($file_type == 'image/gif' && self::IsAnimated($filesrc))
    {
      return $file;
    }

    if ($resizeType !== BX_RESIZE_IMAGE_EXACT && $resizeType !== BX_RESIZE_IMAGE_PROPORTIONAL_ALT)
      $resizeType = BX_RESIZE_IMAGE_PROPORTIONAL;

    if (!is_array($arSize))
      $arSize = array();
    if (!array_key_exists("width", $arSize) || intval($arSize["width"]) <= 0)
      $arSize["width"] = 0;
    if (!array_key_exists("height", $arSize) || intval($arSize["height"]) <= 0)
      $arSize["height"] = 0;
    $arSize["width"] = intval($arSize["width"]);
    $arSize["height"] = intval($arSize["height"]);


    // Путь к новому файлу WebP
    $output_file = str_ireplace(array('.jpg', '.jpeg', '.gif', '.png'), '.webp', $file["FILE_NAME"]);

    $uploadDirName = COption::GetOptionString("main", "upload_dir", "upload");
    if (mb_strpos($file["SUBDIR"], '/' . $uploadDirName . '/') === 0)
    {
      $file["SUBDIR"] = ltrim(mb_substr($file["SUBDIR"], mb_strlen('/' . $uploadDirName . '/')), '/');
    }

    // Убираем слева / слэш, т.к. от medialibrary остается
    $file["SUBDIR"] = ltrim($file["SUBDIR"], '/');

    if ($resizeType === BX_RESIZE_IMAGE_EXACT
      && $file['WIDTH'] > $arSize["width"]
      && $file['HEIGHT'] > $arSize["height"])
    {
      $resize = true;
    } else if (($arSize["width"] != 0 && $arSize["height"] != 0)
      && ($resizeType === BX_RESIZE_IMAGE_PROPORTIONAL
        || $resizeType === BX_RESIZE_IMAGE_PROPORTIONAL_ALT)
      && ($file['WIDTH'] > $arSize["width"] || $file['HEIGHT'] > $arSize["height"]))
    {
      $resize = true;
    } else
    {
      $resize = false;
    }

    // Вычисляем новый путь файла
    if ($resize)
    {
      $cacheImageDir = "/" . $uploadDirName . "/resize_cache/" . $file["SUBDIR"] . "/" . $arSize["width"] . "_" . $arSize["height"] . "_" . $resizeType . (is_array($arFilters) ? md5(serialize($arFilters)) : "");
    } else
    {
      $cacheImageDir = "/" . $uploadDirName . '/' . $file["SUBDIR"];
    }

    $cacheImageFile = $cacheImageDir . "/" . $output_file;

    // Если файл WebP уже существует - отдаем его обратно
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . $cacheImageFile))
    {
      if ($bInitSizes)
      {
        $arImageSize = getimagesize($_SERVER['DOCUMENT_ROOT'] . $cacheImageFile);
        $arImageSize[2] = filesize($_SERVER["DOCUMENT_ROOT"] . $cacheImageFile);
      }

      if (!is_array($arImageSize))
      {
        $arImageSize = [0, 0, 0];
      }

      $result['src'] = $cacheImageFile;
      $result['width'] = intval($arImageSize[0]);
      $result['height'] = intval($arImageSize[1]);
      $result['size'] = $arImageSize[2];

      return $result;
    }

    $seekResizeFile = $_SERVER['DOCUMENT_ROOT'] . $cacheImageDir . '/' . pathinfo($file["SRC"], PATHINFO_FILENAME) . '.' . pathinfo($file["SRC"], PATHINFO_EXTENSION);

    // Если это bmp — ищем jpg, т.к. ResizeImageGet конвертирует их в jpg
    if ($file["CONTENT_TYPE"] == "image/bmp")
    {
      $seekResizeFile = $_SERVER['DOCUMENT_ROOT'] . $cacheImageDir . '/' . pathinfo($file["SRC"], PATHINFO_FILENAME) . 'jpg';
    }

    // Если уже есть файл ResizeImageGet - делаем триггер чтобы не удалять его
    if (file_exists($seekResizeFile))
    {
      $deleteResize = false;
      $arrResizeFile['src'] = self::LTrimServerName($seekResizeFile);
    } else
    {
      // Иначе делаем ResizeImageGet
      $deleteResize = true;
      $arrResizeFile = self::ResizeImageGet($file, $arSize, $resizeType, $bInitSizes, $arFilters, $bImmediate, $jpgQuality);
    }

    // Делаем WebP
    $gd_info = gd_info();

    if ($gd_info['WebP Support'] === true)
    {
      $quality = (is_numeric($jpgQuality)) ? $jpgQuality : -1;
      $resultWebp = self::ConvertImageWebp($arrResizeFile['src'], $quality);
    }


    if ($resultWebp === false)
    {
      return false;
    } else
    {
      // Если есть триггер, то удаляем файл ResizeImageGet
      if ($deleteResize)
      {
        if (!unlink($_SERVER['DOCUMENT_ROOT'] . $arrResizeFile['src']))
        {
          trigger_error("File \$arrResizeFile['src'] for SBFile::ResizeImageGetWebp was not deleted.", E_USER_WARNING);
        }
      }

      // Если нужны размеры - вычисляем
      if ($bInitSizes)
      {
        $arImageSize = getimagesize($_SERVER['DOCUMENT_ROOT'] . $cacheImageFile);
        $arImageSize[2] = filesize($_SERVER["DOCUMENT_ROOT"] . $cacheImageFile);
      }

      if (!is_array($arImageSize))
      {
        $arImageSize = [0, 0, 0];
      }

      // Подготавливаем массив
      $result['src'] = $resultWebp;
      $result['width'] = intval($arImageSize[0]);
      $result['height'] = intval($arImageSize[1]);
      $result['size'] = $arImageSize[2];

      return $result;
    }
  }
}
