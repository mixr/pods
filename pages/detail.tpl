<?php
$id = (int) $id;
$type = empty($type) ? 'news' : $type;
$Record = new Pod($type, $id);

echo $Record->showTemplate('detail');
