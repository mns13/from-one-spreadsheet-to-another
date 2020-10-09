<?php
require_once __DIR__. "/vendor/autoload.php";

$client = new Google_Client();
$client->setAuthConfig(__DIR__.'/credentials.json');
$client->setScopes([Google_Service_Sheets::SPREADSHEETS]);
$client->setAccessType('offline');

$service = new Google_Service_Sheets($client);


/**
 * Get Sheets Id
 * @spreadsheetId
 * 
 * return array
 */
function getSheetsId($spreadsheetId){
  global $service;

  $sheetsIdArr = array();

  $get = $service->spreadsheets->get($spreadsheetId)->sheets;
  
  foreach ($get as $item) {
  
    $sheetsIdArr[] = $item->properties->sheetId;
  
  }

  return $sheetsIdArr;

}


/**
 * get Rows Data
 * 
 * @spreadsheetId
 */
function getRowsData($spreadsheetId, $sheetId){
  global $service;

  $response = $service->spreadsheets->get($spreadsheetId, ['includeGridData'=>true]);

  $sheets = $response->getSheets();
  
  // get all sheets id
  foreach ($sheets as $sheet) {

    $sheetsIdArr[] = $sheet->properties->sheetId;
    
  }

  // get index of sheetId
  $index = array_search($sheetId, $sheetsIdArr);

  return $rowsData = $sheets[$index]->data[0]->rowData;

}


/**
 * Add Temporary Sheet
 * 
 * @spreadsheetId
 */
function addTmpSheet($spreadsheetId){
  global $service;

  $addSheetReq = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([

    'requests' => [

      'addSheet' => [

        'properties' => [

          // 'sheetId' => 0, set automatically
          'title' => 'TemporarySheet',
          // 'index' => 0, set automatically
          'sheetType' => 'GRID',

        ]

      ]

    ],
    'includeSpreadsheetInResponse' => false,
    'responseRanges' => [
      "string"
    ],
    'responseIncludeGridData' => false

  ]);

  return $result = $service->spreadsheets->batchUpdate($spreadsheetId, $addSheetReq);

}


/**
 * CopyPasteRequest
 * 
 * @spreadsheetId
 * @sourceSheetId
 * @destinationSheetId
 * @startIndexSource
 * @endIndexSource
 * @startIndexDest
 * 
 * all we need to change manually are column indices if we need
 * 
 */
function copyPaste($spreadsheetId, $sourceSheetId, $destinationSheetId, $startIndexSource, $endIndexSource, $startIndexDest, $endIndexDest){

  global $service;

  $request = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([

    'requests' => [

      'copyPaste' => [
        'source' => [
          'sheetId' => $sourceSheetId,
          'startRowIndex' => $startIndexSource,
          'endRowIndex' => $endIndexSource,
          'startColumnIndex' => 0,
          'endColumnIndex' => 11
        ],
        'destination' => [
          'sheetId' => $destinationSheetId,
          'startRowIndex' => $startIndexDest,
          'endRowIndex' => $endIndexDest,
          'startColumnIndex' => 0,
          'endColumnIndex' => 11
        ],
        'pasteType' => 'PASTE_NORMAL',
        'pasteOrientation' => 'NORMAL'
      ]

    ]

  ]);

  return $result = $service->spreadsheets->batchUpdate($spreadsheetId, $request);

}

/**
 * CopyTo another spreadsheet
 * 
 * Source @spreadsheetId
 * Source @sheetId
 * Destination @spreadsheetId
 */
function copyTo($spreasheetIdFrom, $sheetId, $spreadsheetIdTo){
  global $service;

  $request = new Google_Service_Sheets_CopySheetToAnotherSpreadsheetRequest([

    'destinationSpreadsheetId' => $spreadsheetIdTo

  ]);

  // $request = new Google_Service_Sheets_CopySheetToAnotherSpreadsheetRequest();
  // $request->setDestinationSpreadsheetId = $spreadsheetIdTo;

  return $result = $service->spreadsheets_sheets->copyTo($spreasheetIdFrom, $sheetId, $request);
}


/**
 * Remove temporary sheet
 * @spresheetId
 * @sheetId
 */
 function removeSheet($spreadsheetId, $sheetId){

  global $service;

  $request = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
    'requests' => [
      'deleteSheet' => [
        'sheetId' => $sheetId
      ]
    ]
  ]);

  return $result = $service->spreadsheets->batchUpdate($spreadsheetId, $request);

 }

#############
### define Spreadsheets Id

$firstSpreadsheetId = '1FS5_xR8YqkBnQPBtL2-r5ieNik-QOVILke5GWnxHovM';
$secondSpreadsheetId = '1gK_T4WgQ-3j5YdrqzCSgUw4WrlVCL9tCjiHlOJ18I5s';


###########
### Add Temporary Sheet In First Spreadsheets
addTmpSheet($firstSpreadsheetId);


#############
### define Sheets Id First Spreadsheet

$firstSheetArr = getSheetsId($firstSpreadsheetId);
$firstTmpSheet = $firstSheetArr[count($firstSheetArr)-1];


############# @firstSpreadsheet
### Copy every fourth row from root sheet [@sheetIdFrom]
### to temporary sheet [@sheetIdTo]

$sheetIdFrom = $firstSheetArr[0];
$sheetIdTo = $firstTmpSheet;

// get rows counter to now how many rows in root sheet
$rowsCounter = getRowsData($firstSpreadsheetId, $sheetIdFrom);

for($i = 0, $d = 0; $i < count($rowsCounter); $i++):
  if(($i+1)%4 != 0) continue;
  copyPaste($firstSpreadsheetId, $sheetIdFrom, $sheetIdTo, $i, ($i+1), $d, ($d+1));
  $d++;
endfor;


#############
### copy temporary @sheetId from @firstSpreadsheet
### to @secondSpreadsheet

$test = copyTo($firstSpreadsheetId, $firstTmpSheet, $secondSpreadsheetId);


############# @secondSpreadsheetId
### define Sheets Id

$secondSheetsArr = getSheetsId($secondSpreadsheetId);
$secondTmpSheet = $secondSheetsArr[count($secondSheetsArr)-1];


#############
### working with data in @secondSpreadsheet
### copy every row from temporary(copied) @tmpSheet
### to root @rootSheet
### and log every row, that was copied

$tmpSheet = $secondTmpSheet;
$rootSheet = $secondSheetsArr[0];

// count(tmpSheetRows) allow to know how many rows in tmp sheet
$tmpSheetRows = getRowsData($secondSpreadsheetId, $tmpSheet);

// count(rootSheetRows) allow to know startIndex of root sheet for append effect
$rootSheetRows = getRowsData($secondSpreadsheetId, $rootSheet);

for($i = 0, $d = 0 + count($rootSheetRows); $i < count($tmpSheetRows); $i++, $d++):

  copyPaste($secondSpreadsheetId, $tmpSheet, $rootSheet, $i, ($i+1), $d, ($d+1));

  $int = $i+1;
  if($int%10 == 0 && $int > 19) $c = 'ieth';
  if($int%10 == 1 && $int > 19 || $int == 1) $c = 'st';
  if($int%10 == 2) $c = 'nd';
  if($int%10 == 3) $c = 'rd';
  if($int%10 >= 4 && $int%10 <= 19) $c = 'th';

  echo $int . $c ." row was succesfully copied." . "<br>";

endfor;

#############
### working with data in @Spreadsheet's
### delete temporary @sheetId's

removeSheet($firstSpreadsheetId, $firstTmpSheet);
removeSheet($secondSpreadsheetId, $secondTmpSheet);