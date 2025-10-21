<?php
    /*
      input
        bid:string
        changed: sring ('true'\ or 'false')
    */
use App\Controllers\ReturnDataController as ReturnDataController;


$authorized = Authorize::authorized($_POST);
if (!$authorized){
  ReturnDataController::returnNotAuthorized();
  die;
}
if ($_POST['checked'] =='true'){
  $weight = 9;
}
else{
  $weight = 0;
}
$bid =intval($_POST['bid']);
$update = BibleModel::updateWeight($bid, $weight);
ReturnDataController::returnData($update);
die;
