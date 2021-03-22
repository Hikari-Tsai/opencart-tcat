<?php
	/**
	* @author Hikari at Quantum Music
	* @version V1.1
	* @package utf8
	*/
//連線方法:
	//傳檔案到伺服器上，呼叫http://URL/tcat-logistics.php?username={}&password={} 即可自動下載託運單匯入檔
if (($_GET["username"] === "<<輸入帳號>>") && ($_GET["password"] === "輸入密碼")) { 
	$link = mysqli_connect("localhost","<<SQL帳號>>","<<SQL密碼>>","<<資料庫名稱>>");
	mysqli_query($link, "set names utf8");
	$data=mysqli_query($link,"
		SELECT  oc_order.order_id, shipping_lastname, shipping_firstname, email, telephone, shipping_address_1, oc_order_product.name, quantity, oc_order.total, order_status_id, comment, shipping_zone, shipping_city, value
		FROM oc_order, oc_order_product
		LEFT JOIN oc_order_option
		ON oc_order_product.order_product_id = oc_order_option.order_product_id
		WHERE oc_order.order_id = oc_order_product.order_id
		AND (order_status_id =17 OR order_status_id =19) #如果有其他需求請自行添加
	");//從contact資料庫中選擇17已付款、19貨到付款以及27來回件
	
	$filename="shipping-sheet-" . date("YmdHis") . ".csv"; 
	header("Content-type: text/x-csv");
	//header("Content-type: text/html");
	header("Content-Disposition: attachment; filename=" . $filename);
	//中文字串分割程式
	/**
	* @version $Id: str_split.php 10381 2008-06-01 03:35:53Z pasamio $
	* @package utf8
	* @subpackage strings
	*/
	function utf8_str_split($str, $split_len = 1)
	{
	    if (!preg_match('/^[0-9]+$/', $split_len) || $split_len < 1)
	        return FALSE;
	 
	    $len = mb_strlen($str, 'UTF-8');
	    if ($len <= $split_len)
	        return array($str);
	 
	    preg_match_all('/.{'.$split_len.'}|[^\x00]{1,'.$split_len.'}$/us', $str, $ar);
	 
	    return $ar[0];
	}

	$fromname = '<<寄件人資訊>>';
	$fromtel = '<<寄件人電話>>';
	$fromadd = '<<寄件人地址>>';
	$COD = '19';//貨到付款status id，當訂單狀態等於COD則會自動帶入貨到收款金額，否則一律帶0
	//$today = date("Ymd");  

	//$tomorrow = date("Ymd",strtotime("+1 day")); 
	//預定出貨日，預設為印單日，逢周日順延
	//預定到貨日，預設隔日抵達，逢周日順延
	if (date("w")=='0'){
		$today = date("Ymd",strtotime("+1 day"));
		$ETA = date("Ymd",strtotime("+2 day"));
		}elseif (date("w",strtotime("+1 day"))=='0'){
			$today = date("Ymd",strtotime("+0 day"));
			$ETA = date("Ymd",strtotime("+2 day"));
		}else{
			$today = date("Ymd");	
			$ETA = date("Ymd",strtotime("+1 day")); 
	}

	$Atime = '4';//不指定送達時間。上午1, 下午2, 不指定4 
	$content = "訂單編號, 溫層, 距離, 規格, 代收貨款, 收件人姓名, 收件人電話, 收件人手機, 收件人地址, 寄件人姓名, 寄件人電話, 寄件人地址, 出貨日期, 預定配達日期, 預定配達時間, 品名, 易碎物品, 精密儀器, 備註, email, 訂單金額 \n";
	$temp_order_id = '';
	$temp_item_name = '';
	$temp_comment = '';


	for($i=1;$i<=mysqli_num_rows($data);$i++){ //開始逐筆取出資料庫
		$rs=mysqli_fetch_row($data);
		//尺寸判別開始
		if ($rs[7]=='1'){
			$size='1';
			}elseif ($rs[7] <='4'){
				$size='2';
			}else{
				$size='';
		}
		

		//貨到付款判別
		if ($rs[9]==$COD){
			$payment=$rs[8]; 
			}else{
				$payment='0';
		}

		//地址重複填寫判斷開始
		$ADD = utf8_str_split($rs[5],3);//拆出地址欄的前兩組三碼
		$TAI_ZONE = utf8_str_split($rs[11]);//拆出zone的字元到陣列
		$TAI_CITY = utf8_str_split($rs[12]);//拆出city的字元到陣列
		if (count($TAI_CITY)>4){ //修正鄉鎮市區多填城市的問題
			
			$TAI_CITY_offset = array_slice($TAI_CITY, 3); 
			//echo "$TAI_CITY_offset[0] . $TAI_CITY_offset[1] . $TAI_CITY_offset[2] . test";
		}else{
			$TAI_CITY_offset = $TAI_CITY;
		}

		if ($TAI_ZONE[0] == "臺"){//調整[臺]與[台]
			$tai_mod_zone = "台" . $TAI_ZONE[1] . $TAI_ZONE[2];
		}else{
			$tai_mod_zone = $rs[11];
		}
		if ($TAI_CITY_offset[0] == "臺"){//調整[臺]與[台]
			//if ($TAI_CITY[3] == "臺"){//調整[臺]與[台]，考慮城市部分重複撰寫
			//	$tai_mod_city = "台" . $TAI_CITY[1] . $TAI_CITY[2] . "台" . $TAI_CITY[4] . $TAI_CITY[5];
			//}else
			$tai_mod_city = "台" . $TAI_CITY_offset[1] . $TAI_CITY_offset[2];// . $TAI_CITY[3] . $TAI_CITY[4] . $TAI_CITY[5];
		}else{
			$tai_mod_city = $TAI_CITY_offset[0] . $TAI_CITY_offset[1] . $TAI_CITY_offset[2];
		}

		if ($tai_mod_zone == $ADD[0]){//縣市重複判斷，比對地址1-3碼數字與zone欄位是否相同
			$add_zone = '';
			}else{
				$add_zone = $tai_mod_zone;
		}
		if ($tai_mod_city == $ADD[1]){//鄉鎮市區重複判斷，比對地址4-6碼數字與city欄位是否相同
			$add_city = '';
			}elseif ($tai_mod_city == $ADD[0]){//鄉鎮市區重複判斷，比對地址1-3碼數字與city欄位是否相同
			}else{
			$add_city = $tai_mod_city;
		
		}
		$mod_address = $add_zone . $add_city . $rs[5];
		$tw_total =  number_format($rs[8],0,'.','');
		$item_name = str_replace(array(",", "\r", "\n", "\r\n", "\n\r"), " ", $rs[6] . $rs[13] . "X" .  $rs[7]);
		$comment = str_replace(array(",", "\r", "\n", "\r\n", "\n\r"), " ",$rs[10]);
		//重新組合備註欄位，並搜尋可能會擾亂csv格式的字符
		(int) $current_order_id = $rs[0];

		$str_phone = (string)$rs[4];

		//若一筆訂單購買多項產品，則迴圈寫入同一列
		if ($current_order_id == $temp_order_id) {

			$temp_item_name = $item_name . "; " . $temp_item_name;//若有三樣以上產品，才能持續串接產品名稱
			$item_name = $temp_item_name;//修正產品名稱，使得$temp_content內部的資料為多產品並列
			//$content .= $temp_content;
		}else{
			$content .= $temp_content;//將上一圈內容寫入csv
			$temp_item_name = $item_name;//將本圈購買產品存入暫存資料
		}


		//以下用途為暫存資料，在下次回圈時可以用來比較
		(int) $temp_order_id = $rs[0];
		//產品暫存名稱移到上面的if內
		//以下為標準程式，含完整品名
		$temp_content = "$rs[0], 1, , $size, $payment, $rs[1] $rs[2], , $str_phone, $mod_address, $fromname, $fromtel, $fromadd, $today, $ETA, $Atime, $item_name ($tw_total), N, N, $comment, $rs[3], $tw_total \n";


		unset($rs);
		unset($mod_address);
		unset($item_name);
		unset($tw_total);
		unset($comment);
		unset($str_phone);
		unset($add_zone);
		unset($add_city);
		unset($mod_address);



	}
	$content .= $temp_content;//補寫最後一個回圈的row
	//$content = mb_convert_encoding($content , "Big5" , "UTF-8");//轉換為BIG5
		echo $content;
		exit;
} else {
	echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
	echo "帳號密碼錯誤，資料庫拒絕回應";
}

?>
