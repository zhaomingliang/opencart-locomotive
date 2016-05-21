<?php
$conn = @mysql_connect("localhost", "root", "root") or die("Can not connect mysql!");
@mysql_select_db("opencart") or die("database not exist!");
@mysql_query("set names 'utf8'");
$auto_cate = true;//是否自动建立分类 true | false
global $language_id, $store_id;
$language_id = 1;//发布的语言ID
$store_id = 0; //商店ID

if(empty($_POST)){ //刷新分类
	echo '<select name="categories">';
	foreach(get_category_tree() as $key => $value){// 输出分类菜单
		echo "<option value='" . $value['id'] . "'>" . $value['text'] . "</option>\r\n";
	}
	echo '</select>';
	exit();
}

if(!empty($_POST['categories']) && $auto_cate == true){//自动建立分类
	$parent_id = 0;
	$tocats_arr = array();
	$cats = array_filter(explode('|||', $_POST['categories']));
	foreach($cats as $key => $value){
		$parent_id = create_category($value, $parent_id);
		$tocats_arr[] = $parent_id; //各级分类ID写入数组
	}
	$_POST['category_id'] = $parent_id;
}

//分类所属商店
if(sizeof($tocats_arr) > 0){
	foreach($tocats_arr as $key => $value){
		$tocats_query = "INSERT INTO `oc_category_to_store` (`category_id`, `store_id`) VALUES ('$value', '$store_id');";
		mysql_query($tocats_query);
	}
}

if(empty($_POST['name']) or empty($_POST['price'])){//判断必填项
	exit('Some is Empty!');
}
$manufacturer_name = addslashes($_POST['manufacturer_name']);
$manufacturer_id = !empty($manufacturer_name) ? create_manufacturer($manufacturer_name) : '';//自动厂商
$name = addslashes($_POST['name']);
$description = addslashes($_POST['description']);
$meta_description = addslashes($_POST['meta_description']);
$meta_keyword = addslashes($_POST['meta_keyword']);
$model = !empty($_POST['model']) ? strval($_POST['model']) : 'N' . strval(mt_rand());
$quantity = !empty($_POST['quantity']) ? $_POST['quantity'] : rand(500, 999);
$price = (float)($_POST['price']);
$date_available = date('Y-m-d');
$weight = !empty($_POST['weight']) ? $_POST['weight'] : '0';
$length = !empty($_POST['length']) ? $_POST['length'] : '0';
$width = !empty($_POST['width']) ? $_POST['width'] : '0';
$height = !empty($_POST['height']) ? $_POST['height'] : '0';
$sort_order = !empty($_POST['sort_order']) ? $_POST['sort_order'] : '1';
$date_added = date('Y-m-d H:i:s');
$date_modified = date('Y-m-d H:i:s');
$sprice = !empty($_POST['sprice']) ? (float)($_POST['sprice']) : '';
$dprice = !empty($_POST['dprice']) ? (float)($_POST['dprice']) : '';
$dprice_qty = !empty($_POST['dprice_qty']) ? (int)($_POST['dprice_qty']) : ''; //打折商品所需达到的购买数量
$date_start = date('Y-m-d');
$date_end = '2031-11-11';

$insert_product_query = "INSERT INTO `oc_product` (`product_id`, `model`, `sku`, `upc`, `location`, `quantity`, `stock_status_id`, `image`, `manufacturer_id`, `shipping`, `price`, `points`, `tax_class_id`, `date_available`, `weight`, `weight_class_id`, `length`, `width`, `height`, `length_class_id`, `subtract`, `minimum`, `sort_order`, `status`, `date_added`, `date_modified`, `viewed`) VALUES ('', '$model', '', '', '', '$quantity', '5', NULL, '$manufacturer_id', '1', '$price', '0', '9', '$date_available', '$weight', '1', '$length', '$width', '$height', '1', '1', '1', '$sort_order', '1', '$date_added', '$date_modified', '0');";
mysql_query($insert_product_query);
$product_id = @mysql_insert_id();
if(!$product_id){
	exit('insert error!');
}else{
	echo "insert success! [PID] $product_id";
}

//添加产品到商店
$insert_pro_to_store_query = "INSERT INTO `oc_product_to_store` (`product_id`, `store_id`) VALUES ('$product_id', '$store_id');";
mysql_query($insert_pro_to_store_query);

//添加产品描述
$insert_product_desc_query = "INSERT INTO `oc_product_description` (`product_id`, `language_id`, `name`, `description`, `meta_description`, `meta_keyword`) VALUES ('$product_id', '$language_id', '$name', '$description', '$meta_description', '$meta_keyword');";
mysql_query($insert_product_desc_query);

//添加产品到分类中
if(!empty($tocats_arr)){
	$curr_cate_id = $_POST['category_id']; //截取当前分类
	$tocurr_cate_query = "INSERT INTO `oc_product_to_category` (`product_id`, `category_id`) VALUES ('$product_id', '$curr_cate_id');";
	mysql_query($tocurr_cate_query);
	foreach($tocats_arr as $key => $value){ // 添加到上级分类中
		$tocats_query = "INSERT INTO `oc_product_to_category` (`product_id`, `category_id`) VALUES ('$product_id', '$value');";
		mysql_query($tocats_query);
	}
}

//添加特价
if(!empty($sprice)){ //customer_group_id 必须在数据库表customer_group中存在 否则特价不显示(默认为1)
	$sprice_query = "INSERT INTO `oc_product_special` (`product_special_id`, `product_id`, `customer_group_id`, `priority`, `price`, `date_start`, `date_end`) VALUES ('', '$product_id', '1', '1', '$sprice', '$date_start', '$date_end');";
	mysql_query($sprice_query);
}

//添加打折价 类似批发价(如10个以上价格)
if(!empty($dprice)){ //customer_group_id 必须在数据库表customer_group中存在 否则打折价不显示(默认为1)
	$dprice_query = "INSERT INTO `oc_product_discount` (`product_discount_id`, `product_id`, `customer_group_id`, `quantity`, `priority`, `price`, `date_start`, `date_end`) VALUES ('', '$product_id', '1', '$dprice_qty', '1', '$dprice', '$date_start', '$date_end');";
	mysql_query($dprice_query);
}

//添加产品TAG
if(!empty($_POST['tags'])){
	$tags_arr = array_filter(explode('|||', $_POST['tags']));
	foreach($tags_arr as $tagkey => $tagvalue){
		$tagvalue = addslashes($tagvalue);
		$insert_tags_query = "INSERT INTO `product_tag` (`product_tag_id`, `product_id`, `language_id`, `tag`) VALUES ('', '$product_id', '$language_id', '$tagvalue');";
		mysql_query($insert_tags_query);
	}
}

//添加相关产品
if(!empty($_POST['related_product'])){
	$related_arr = array_filter(explode('|||', $_POST['related_product']));
	foreach($related_arr as $relkey => $relvalue){
		$relvalue = addslashes($relvalue);
		$pro_desc_query = "SELECT `product_description`.`product_id` FROM ` oc_product_description ` WHERE `product_description`.`name` = '$relvalue' LIMIT 0, 1";
		$pro_desc_result = @mysql_query($pro_desc_query);
		$num = @mysql_num_rows($pro_desc_result);
		if($num > 0){
			$related_id = (int)(mysql_result($pro_desc_result, 0, "product_id"));
			$insert_related_query = "INSERT INTO `product_related` (`product_id`, `related_id`) VALUES ('$product_id', '$related_id');";
			mysql_query($insert_related_query);
		}
	}
}

if(!empty($_POST['images'])){//自动远程图片
	$array_imgs=explode('|||', $_POST['images']);
	$file_imgs= get_images(array_unique(array_filter($array_imgs)), $product_id, "data/category_". $_POST['category_id'] . "/", "image/data/category_". $_POST['category_id'] . "/");

	//var_dump($file_imgs);
	foreach($file_imgs as $key => $value){
		
		if($key=='0'){
			$insert_main_img_query = "UPDATE `oc_product` SET `image` = '$value' WHERE `product`.`product_id` = '$product_id' LIMIT 1;";
			mysql_query($insert_main_img_query);
			
		}else{
			$insert_add_img_query = "INSERT INTO `oc_product_image` (`product_image_id`, `product_id`, `image`, `sort_order`) VALUES ('', '$product_id', '$value', '$key');";
			mysql_query($insert_add_img_query);
		}
	}
}

//添加产品参数特征
if(is_array($_POST['attributes']) && !empty($_POST['attributes'])){ // 自动添加产品参数
	foreach($_POST['attributes'] as $key => $value){
		$group_name = addslashes($key);		
		//$group_name_query = "SELECT `attribute_group`.`attribute_group_id` FROM `attribute_group`, `attribute_group_description` WHERE `attribute_group`.`attribute_group_id` =  `attribute_group_description`.`attribute_group_id` AND `attribute_group_description`.`name` = '$group_name' AND `attribute_group_description`.`language_id` = '$language_id' LIMIT 0, 1"; //查询是否存在已有参数组
		$group_name_query = "SELECT `attribute_group_description`.`attribute_group_id` FROM `oc_attribute_group_description` WHERE `attribute_group_description`.`name` = '$group_name' AND `attribute_group_description`.`language_id` = '$language_id' LIMIT 0, 1"; //查询是否存在已有参数组
		$group_name_result = @mysql_query($group_name_query);
		$numG = @mysql_num_rows($group_name_result);
		if($numG > 0){
			$attgroid = (int)(mysql_result($group_name_result, 0, "attribute_group_id"));
		}else{
			$insert_attr_group_query = "INSERT INTO `oc_attribute_group` (`attribute_group_id`, `sort_order`) VALUES ('', '1');";
			mysql_query($insert_attr_group_query);
			$attgroid = mysql_insert_id();
			$insert_attr_group_desc_query = "INSERT INTO `attribute_group_description` (`attribute_group_id`, `language_id`, `name`) VALUES ('$attgroid', '$language_id', '$group_name');";
			mysql_query($insert_attr_group_desc_query);
		}
		$arr_attrib = explode('|||', $value);//添加产品参数名
		foreach(array_unique(array_filter($arr_attrib)) as $attr_key => $attr_value){
			$array_name = explode('===', $attr_value); //获取参数值
			$attrib_name = addslashes($array_name[0]);
			$attrib_value = addslashes($array_name[1]);
			//$attrib_name_query = "SELECT `attribute`.`attribute_id` FROM `attribute`, `attribute_description` WHERE `attribute`.`attribute_id` = `attribute_description`.`attribute_id` AND `attribute_description`.`name` = '$attrib_name' AND `attribute_description`.`language_id` = '$language_id' LIMIT 0, 1"; //查询是否存在已有属性名
			$attrib_name_query = "SELECT `attribute_description`.`attribute_id` FROM `oc_attribute_description` WHERE `attribute_description`.`name` = '$attrib_name' AND `attribute_description`.`language_id` = '$language_id' LIMIT 0, 1"; //查询是否存在已有属性名
			$attrib_name_result = @mysql_query($attrib_name_query);
			$numA = mysql_num_rows($attrib_name_result);
			if($numA > 0){
				$attid = (int)(mysql_result($attrib_name_result, 0, "attribute_id"));
			}else{
				$insert_att_id_query = "INSERT INTO `oc_attribute` (`attribute_id`, `attribute_group_id`, `sort_order`) VALUES ('', '$attgroid', '1');";
				mysql_query($insert_att_id_query);
				$attid = mysql_insert_id();
				$insert_att_name_query = "INSERT INTO `oc_attribute_description` (`attribute_id`, `language_id`, `name`) VALUES ('$attid', '$language_id', '$attrib_name');";
				mysql_query($insert_att_name_query);
			}
			$insert_pro_att_query = "INSERT INTO `oc_product_attribute` (`product_id`, `attribute_id`, `language_id`, `text`) VALUES ('$product_id', '$attid', '$language_id', '$attrib_value');";
			mysql_query($insert_pro_att_query);
		}
	}
}

//添加options属性 options[Size|select] = 'S|||M|||L|||XL'
if(is_array($_POST['options']) && !empty($_POST['options'])){ // 自动添加属性
	foreach($_POST['options'] as $key => $value){
		$array_name = explode('|', $key); // 判断是哪种表单 select | checkbox | radio | textarea
		$array_name[1] = empty($array_name[1]) ? 'select' : $array_name[1];
		$option_type = $array_name[1];
		$option_name = addslashes($array_name[0]);
		$option_name_query = "SELECT `option`.`option_id`, `option`.`type`, `option_description`.`name` FROM `option`, `option_description` WHERE `option`.`option_id` = `option_description`.`option_id` AND `option`.`type` = '$option_type' AND `option_description`.`name` = '$option_name' AND `option_description`.`language_id` = '$language_id' LIMIT 0, 1"; //查询是否存在已有属性名
		$option_name_result = @mysql_query($option_name_query);
		$num = @mysql_num_rows($option_name_result);
		if($num > 0){
			$optid = (int)(mysql_result($option_name_result, 0, "option_id"));
		}else{
			$insert_opt_id_query = "INSERT INTO `oc_option` (`option_id`, `type`, `sort_order`) VALUES ('', '$option_type', '1');";
			mysql_query($insert_opt_id_query);
			$optid = @mysql_insert_id();
			$insert_opt_name_query = "INSERT INTO `oc_option_description` (`option_id`, `language_id`, `name`) VALUES ('$optid', '$language_id', '$option_name');";
			mysql_query($insert_opt_name_query);
		}
		
		if($option_type == 'text' or $option_type == 'textarea' or $option_type == 'file' or $option_type == 'date' or $option_type == 'time' or $option_type == 'datetime'){ // 如果是(文本 | 文件 | 时间)属性就执行下面插入操作
			$insert_pro_option_query = "INSERT INTO `product_option` (`product_option_id`, `product_id`, `option_id`, `option_value`, `required`) VALUES ('', '$product_id', '$optid', '$value', '1');";
			mysql_query($insert_pro_option_query);
		}else{ // 如果是(select | checkbox | radio)属性就执行下面插入操作
			$array_option = explode('|||', $value);//添加属性值
			$optsort = 100; //新插入的属性值从100开始排序
			foreach(array_unique(array_filter($array_option)) as $opt_key => $opt_value){
				$optsort++;
				preg_match("~\[\[\[(.*?)\]\]\]~i", $opt_value, $optprice);//提取属性价格
				$opt_price = !empty($optprice[1]) ? $optprice[1] : 0;
				$opt_value = preg_replace("~\[\[\[([\s\S]*?)\]\]\]~i", "", $opt_value); //提取属性值
				$opt_value = trim($opt_value);
				$option_value_query = "SELECT `option_value`.`option_value_id`, `option_value`.`option_id`, `option_value_description`.`name` FROM `option_value`, `option_value_description` WHERE `option_value`.`option_value_id` = `option_value_description`.`option_value_id` AND `option_value`.`option_id` = '$optid' AND `option_value_description`.`name` = '$opt_value' AND `option_value_description`.`language_id` = '$language_id' LIMIT 0, 1";
				$option_value_result = @mysql_query($option_value_query);
				$num = @mysql_num_rows($option_value_result);
				if($num > 0){
					$optvid = (int)(mysql_result($option_value_result, 0, "option_value_id"));
				}else{
					$insert_optv_id_query = "INSERT INTO `oc_option_value` (`option_value_id`, `option_id`, `sort_order`) VALUES ('', '$optid', '$optsort');";
					mysql_query($insert_optv_id_query);
					$optvid = @mysql_insert_id();
					$insert_opt_value_query = "INSERT INTO `oc_option_value_description` (`option_value_id`, `language_id`, `option_id`, `name`) VALUES ('$optvid', '$language_id', '$optid', '$opt_value');";
					mysql_query($insert_opt_value_query);
				}
				if($opt_key == 0){
					global $pro_optid;
					$insert_pro_option_query = "INSERT INTO `oc_product_option` (`product_option_id`, `product_id`, `option_id`, `option_value`, `required`) VALUES ('', '$product_id', '$optid', '', '1');";
					mysql_query($insert_pro_option_query);
					$pro_optid = @mysql_insert_id();
				}
				$insert_pro_option_value_query = "INSERT INTO `oc_product_option_value` (`product_option_value_id`, `product_option_id`, `product_id`, `option_id`, `option_value_id`, `quantity`, `subtract`, `price`, `price_prefix`, `points`, `points_prefix`, `weight`, `weight_prefix`) VALUES ('', '$pro_optid', '$product_id', '$optid', '$optvid', '999', '1', '$opt_price', '+', '0', '+', '0', '+');";
				mysql_query($insert_pro_option_value_query);
			}
		}
	}
}

$reviews_user = "Adela|||Beverly|||Delia|||Elvira|||Jennifer|||Letitia|||Marian|||Rachel|||Tammy|||Victoria|||Zenobia|||Aubrey|||Barnett|||Charles|||Dempsey|||Elmer|||Frederic|||Gordon|||Horace|||Ingram|||James|||Kevin|||Lucien|||Marshall|||Mick|||Newman|||Oswald";//本接口支持回复，可以采集回复的用户名，如果没有，可以从这些默认用户中选择，多个用户之间使用|||分开
if(!empty($_POST['reviews'])){//添加产品评论
	$reviews = array_unique(array_filter(explode('|||', $_POST['reviews'])));
	$reviews_user = explode('|||', $reviews_user);
	$reviews_time = date('Y-m-d H:i:s'); //写入数据库的回复时间格式请用此格式(2012-07-21 00:00:00)
	foreach($reviews as $rkey=>$rvalue){
		if(isset($_SESSION['r_time'])){
			$_SESSION['r_time'] = $_SESSION['r_time']+mt_rand(60,300);
		}else{
			$_SESSION['r_time'] = mt_rand(180,300);
		}
		$r_time = (int)$_SESSION['r_time'];//随机增加的时间秒数，介于1秒至24小时之间，确保在文章发表时间之后评论
		$reviews_time = date('Y-m-d H:i:s', strtotime($reviews_time)+$r_time);
		if(!empty($rvalue)){
			preg_match("~\[\[\[user#(.*?)\]\]\]~i", $rvalue, $user);//提取评论用户名
			preg_match("~\[\[\[time#(.*?)\]\]\]~i", $rvalue, $time);//提取回复时间
			preg_match("~\[\[\[star#(.*?)\]\]\]~i", $rvalue, $star);//提取评论星级
			if(strpos($time[1], '/') !== false){ //如果时间格式是(21/07/2012)则需转化
				$time1 = explode('/', trim($time[1]));
				$time2[0] = $time1[2];
				$time2[1] = $time1[1];
				$time2[2] = $time1[0];
				$time[1] = implode('-', $time2);
			}
			$reviews_author = !empty($user[1])?trim($user[1]):$reviews_user[array_rand($reviews_user)];
			$reviews_time = !empty($time[1])?trim(str_replace('.', '-', $time[1])):$reviews_time;
			$reviews_text = trim(strip_tags(preg_replace("~\[\[\[([\s\S]*?)\]\]\]~i", "", $rvalue)));
			$reviews_star = !empty($star[1])?(int)(trim($star[1])):5; //评论星级
			$reviews_status = 1; //显示评论
			$sql = "INSERT INTO `oc_review` (`review_id`, `product_id`, `customer_id`, `author`, `text`, `rating`, `status`, `date_added`, `date_modified`) " .
						"values('', '$product_id', '0', '$reviews_author', '$reviews_text', '$reviews_star', '$reviews_status', '$reviews_time', '0000-00-00 00:00:00')";
			mysql_query($sql);
			//$reviews_id = @mysql_insert_id();//评论ID
		}		
	}
}

function get_category_tree($parent_id = '0', $spacing = '', $category_tree_array = ''){ // 获取分类写入数组
	global $language_id;
    if(!is_array($category_tree_array)) $category_tree_array = array();
    if(sizeof($category_tree_array) < 1) $category_tree_array[] = array('id' => '0', 'text' => ' -- SELECT -- ');
	$query = "SELECT c.category_id, cd.name, c.parent_id
                                FROM category c, category_description cd 
                                WHERE c.category_id = cd.category_id 
                                and cd.language_id = '" . (int)$language_id . "' 
                                and c.parent_id = '" . (int)$parent_id . "' 
                                order by c.sort_order, cd.name";
	$result = mysql_query($query);
	while($row = mysql_fetch_assoc($result)){
		$mark = '&nbsp;&nbsp;';
		$category_tree_array[] = array('id' => $row["category_id"], 'text' => $spacing . $row["name"] . $mark);
    	$category_tree_array = get_category_tree($row["category_id"], $spacing . '&nbsp;&nbsp;&nbsp;', $category_tree_array);
	}
    return $category_tree_array;
}

function create_category($category_name, $parent_id){ // 创建分类
	global $language_id;
	$exist_same_cate = 0;//判断是否存在同名目录
    $cate_name_query = "SELECT  c.category_id, cd.name 
                         FROM oc_category c, oc_category_description cd
                         WHERE  c.parent_id = '" . (int)$parent_id . "'
                         and c.category_id = cd.category_id
                         and cd.language_id = '" . (int)$language_id . "'
                         order by sort_order, cd.name";
	$cate_name_result = mysql_query($cate_name_query);
	while($row = mysql_fetch_assoc($cate_name_result)){
    	if($category_name == $row["name"]){
			$exist_same_cate = 1;
			$parent_id = $row["category_id"];
    	}
    }
	if($exist_same_cate == 0){
		$sort_query = "SELECT max(sort_order) as oc_sort_max FROM oc_category";
		$sort_result = mysql_query($sort_query);
		$sort_num = @mysql_num_rows($sort_result);
		$sort_max = mysql_result($sort_result, 0, "sort_max");
		$cate_num_query = "SELECT count(*) as oc_cate_num FROM oc_category";
		$cate_num_result = mysql_query($cate_num_query);
		$cate_num = mysql_result($cate_num_result, 0, "cate_num");
		if($sort_num > 0){
			if($cate_num > $sort_max){
				$sort_order =  $cate_num + 1;
			}else{
				$sort_order =  $sort_max + 1;
			}
		}else{
			$sort_order = 1;
		}
		$cate_insert_query = "INSERT INTO `oc_category` (`category_id`, `image`, `parent_id`, `top`, `column`, `sort_order`, `status`, `date_added`, `date_modified`) VALUES ('', '', '" . (int)$parent_id . "', '0', '1', '" . (int)($sort_order) . "', '1', '" . date("Y-m-d H:i:s") . "', '" . date("Y-m-d H:i:s") . "');";
		mysql_query($cate_insert_query);
		$parent_id = @mysql_insert_id();
		$cate_des_insert_query = "INSERT INTO `oc_category_description` (`category_id`, `language_id`, `name`, `description`, `meta_description`, `meta_keyword`) VALUES ('" . (int)$parent_id . "', '" . (int)$language_id . "', '" .  addslashes($category_name) . "', '" .  addslashes($category_name) . "', '" .  addslashes($category_name) . "', '" .  addslashes($category_name) . "');";
		mysql_query($cate_des_insert_query);
	}
    return $parent_id;
}


function create_manufacturer($manufacturer_name){ // 创建品牌商
	global $language_id, $store_id;
	$manufacturer_query = "SELECT `manufacturer_id`,  FROM `oc_manufacturer` WHERE `name` = '$manufacturer_name' limit 0,1";
	$manufacturer_result = mysql_query($manufacturer_query);
	$exist = @mysql_num_rows($manufacturer_result);
	if($exist){
		$manufacturer_id = mysql_result($manufacturer_result, 0, "manufacturer_id");
		return $manufacturer_id;
	}else{       
		$insert_manufacturer_query = "INSERT INTO `oc_manufacturer` (`manufacturer_id`, `name`, `image`, `sort_order`) VALUES ('', '$manufacturer_name', '', '1');";
		mysql_query($insert_manufacturer_query);
		$manufacturer_id = mysql_insert_id();
		$manufacturer_store_query = "INSERT INTO `oc_manufacturer_to_store` (`manufacturer_id`, `store_id`) VALUES ('$manufacturer_id', '$store_id');";
		mysql_query($manufacturer_store_query);
		return $manufacturer_id;
	}
}
function get_images($imgs_array, $products_id, $sub_dir, $imgs_dir){
	if(count($imgs_array) == 0) return false;
	global $new_imgs;
	$new_imgs = array();
	$num = 0;
	foreach($imgs_array as $key => $value){	
		$ext =strrchr($value,".");
		if($ext != ".gif" && $ext != ".jpg" && $ext != ".png" && $ext != ".bmp" && $ext != ".gif" && $ext != ".JPG" && $ext != ".PNG" && $ext != ".BMP") return false;
		if(!is_dir($imgs_dir)){
			@mkdir($imgs_dir, 0777);
			@chmod($imgs_dir, 0777);
		}
		$value = trim($value);
		//------bof 提取图片原始名称-------//
		//$imgArray = explode('/',$value);
		//$imgName = strtolower(array_shift(explode('.',array_pop($imgArray))));
		//------eof 提取图片原始名称-------//
		$value = str_replace(' ', '%20', $value);
		$value = str_replace('&amp;', '&', $value);

		$curl_handle = curl_init();		
		curl_setopt($curl_handle, CURLOPT_URL, $value);
		curl_setopt($curl_handle, CURLOPT_HEADER, false);
		curl_setopt($curl_handle, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; SeaPort/1.2; Windows NT 5.1; SV1; InfoPath.2)");
		curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1); 
		curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 10);
		$img = curl_exec($curl_handle);
		curl_close($curl_handle);
		
		if($num == 0){			
			$handle = @fopen($imgs_dir.$products_id.'pro'.$ext, "w");
			$new_imgs[] = $sub_dir.$products_id.'pro'.$ext;
			//$handle = @fopen($imgs_dir.$products_id.'pro-'.$imgName.$ext, "w"); //用图片原始名称命名
			//$new_imgs[] = $sub_dir.$products_id.'pro-'.$imgName.$ext; //用图片原始名称命名
		}else{
			$handle = @fopen($imgs_dir.$products_id.'pro'.'_'.$num.$ext, "w");
			$new_imgs[] = $sub_dir.$products_id.'pro'.'_'.$num.$ext;
			//$handle = @fopen($imgs_dir.$products_id.'pro-'.$imgName.'_'.$num.$ext, "w"); //用图片原始名称命名
			//$new_imgs[] = $sub_dir.$products_id.'pro-'.$imgName.'_'.$num.$ext; //用图片原始名称命名
		}	
		if (is_dir($imgs_dir)){  

		}else{
			//第三个参数是“true”表示能创建多级目录，iconv防止中文目录乱码
			$res=mkdir(iconv("UTF-8", "GBK", $imgs_dir),0777,true); 
			if ($res){
				
			}else{
				exit();
			}
		}
		
		fwrite($handle, $img);
		fclose($handle);
		$num++;
	}
	return $new_imgs;
} 
mysql_close();
?>