<?php

include "vendor/autoload.php";

$model =  new \laravelModel\XinModel();

echo $model->getCurrentStoreId();