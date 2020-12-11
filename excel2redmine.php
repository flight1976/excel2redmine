#!/usr/bin/php -f
<?php
/*
* v0.3
*
*
*/

# input params check
# 檢查輸入參數
if ($argc != 6) {
	echo "version: 0.3\n";
	echo "Usage: $argv[0] <excel-file-location> <excel-sheet-name> <project-id-string> <project-name> <read/write>\n";
	exit();
}

$excel_file = $argv[1];
$sheet_name = $argv[2];
$main_p_name = $argv[4];
$pid = $argv[3];
$action = $argv[5];


require 'config.inc.php';
require 'vendor/autoload.php';
require 'func.inc.php';
use PhpOffice\PhpSpreadsheet\Shared\Date;

$worksheetName = "$excel_file";



$client = new Redmine\Client($redmine_web, $redmine_api_key);
$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
$reader->setReadDataOnly(true);
$reader->setLoadSheetsOnly([$sheet_name]);

#$spreadsheet = $reader->load($worksheetName);
#$worksheetNames = $reader->listWorksheetNames($worksheetName);
#$worksheetData = $reader->listWorksheetInfo($worksheetName);
$spreadsheet = $reader->load($worksheetName);
$worksheet = $spreadsheet->getActiveSheet();


// Get the highest row and column numbers referenced in the worksheet
$highestRow = $worksheet->getHighestRow(); // e.g. 10
$highestColumn = $worksheet->getHighestColumn(); // e.g 'F'
#echo "$highestRow $highestColumn\n";


$highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn); // e.g. 5

$users = get_user_array($redmine_web, $redmine_api_key);
$projects = get_project_array($redmine_web, $redmine_api_key);

#print_r($users);

$main_pid = $pid . "_000";
if (count($projects[$main_pid]) <= 0) {
	echo "主專案: $main_pid -> $main_p_name 不存在!! ..\n";

	if($action=='write'){
		//建立主專案項目
		$client->project->create([
			'name'=>"$main_p_name",
			'identifier'=>"$main_pid",
			'tracker_ids'=>[],
		]);
		echo "建立主專案 $main_pid -> $main_p_name \n";
	}
	exit();
}

$sub_pid_no = 1;
$curent_pid = $main_pid;
for ($row = 2; $row <= $highestRow; ++$row) {
	$serial = $worksheet->getCellByColumnAndRow(3, $row)->getValue();
	$name = $worksheet->getCellByColumnAndRow(4, $row)->getValue();
	$s_time = $worksheet->getCellByColumnAndRow(5, $row);
	$e_time = $worksheet->getCellByColumnAndRow(6, $row);
	$done_percent = $worksheet->getCellByColumnAndRow(7, $row)->getValue();
	$contact = $worksheet->getCellByColumnAndRow(8, $row)->getValue();
	$contact_other = $worksheet->getCellByColumnAndRow(9, $row)->getValue();
	$desc = $worksheet->getCellByColumnAndRow(10, $row);


	#$done_percent = $done_percent + 0;
	#$done_percent = $done_percent * 100;
	if ($done_percent == '') {
		$done_percent = 0;
	} else {
		$done_percent = (float)$done_percent * 100;
	}

	$doc = $worksheet->getCellByColumnAndRow(8, $row);

	#subject/desc
	$subject = "$serial ". trim($name);

	#轉換日期格式 (工作項目才需要調整)
	if (strpos($serial, '.') >= 1) {
		$s_time = $s_time->getValue();
		$e_time = $e_time->getValue();
		$s_time = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($s_time);
		$e_time = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($e_time);
		$s_time = date('Y-m-d', $s_time);
		$e_time = date('Y-m-d', $e_time);
	}	

	//僅parsing issue(ignore project item)
	// https://www.php.net/manual/en/function.strpos.php
	if (strpos($serial, '.') >= 1) { //工作項目
		//$assign_to = null; //debug use
		if (isset($users[$contact])) {
			$assign_to = (int)$users[$contact]['id'];
		} else {
			$assign_to = null;
		}
		// status_id 代碼查詢 https://stmo.cht.com.tw/redmine/issue_statuses
		// 注意事項: redmine內流程要手動允許建立新議題時狀態可以直接是 處理中/已結束
		switch ($done_percent) {
			case 0: 
				$status_id = null; //新建立
				break;
                        case 100: 
                                $status_id = 5; //已結束
                                break;
			default:
				$status_id = 2;	//處理中
		}

		echo "$current_pid $serial $s_time $e_time $name $done_percent status_id:$status_id '$contact' '$contact_other' $assign_to \n";

		if ($action == 'write') {
			//建立redmine issue
			$client->issue->create([
			'project_id'  => "$current_pid",
			'subject'     => "$subject",
			'description' => "$desc",
		 	'start_date' => "$s_time",
			'due_date' => "$e_time",
			'done_ratio' => "$done_percent",
			'status_id' => "$status_id",
			'assigned_to_id' => $assign_to,
			'custom_fields' => [
				[
				'id' => 9,
				'name' => '聯絡人',
				'value' => "$contact_other",
				],
			], //custom_fields

			]); //issue-create
		}

	} else if (is_int($serial)) { //子專案
		$t = str_pad($sub_pid_no, 3, "0", STR_PAD_LEFT);
		$sub_pid = $pid . "_" . $t;
		$current_pid = $sub_pid;
		$parent_id = $projects[$main_pid];
		echo "$main_pid -> $parent_id 子專案: $sub_pid $serial $subject \n";
		if ($action == 'write') {
			//建立子專案項目
			// todo 事先檢查是否已建立
			$client->project->create([
				'name' => "$subject",
				'identifier' => "$sub_pid",
				'parent_id' => "$parent_id",
				'inherit_members' => true,
				'tracker_ids' => [],
				]);
		}
		$sub_pid_no++;
	} else { //錯誤格式資料
	
	}
}//for
