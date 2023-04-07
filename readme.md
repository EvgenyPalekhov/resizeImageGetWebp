# resizeImageGetWebp

Дополнение к Битриксовскому resizeImageGet.
Уменьшает картинку и делает файл формата Webp.

Пример использования:
<pre>
$newFileWebp = SBFile::ResizeImageGetWebp($file, array("width" => 200, "height" => 200), BX_RESIZE_IMAGE_EXACT, true);
</pre>
-- все параметры повторяют стандартный resizeImageGet, то есть можно просто заменить
CFile::ResizeImageGet на SBFile::ResizeImageGetWebp

Установка:
1) Сохранить в папку php_interface/classes/ файл resizeImageGetWebp.class.php
2) Добавить в файл php_interface/init.php строчки (проверить путь к файлу):

<pre>
CModule::AddAutoloadClasses(
        '', // не указываем имя модуля 
        array(
           // ключ - имя класса, значение - путь относительно корня сайта к файлу с классом
                'SBFile' => '/bitrix/php_interface/classes/resizeImageGetWebp.class.php',
        )
);
</pre>
