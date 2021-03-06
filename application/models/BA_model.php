<?php
class BA_model extends CI_Model {

	public function __construct()
	{
		$this->load->database();
	}	
	public function load_catalogue($user_type = ""){
		if ($user_type == "BO"){
			$user_ID = $this->session->userdata("user_ID");	
			$query1 = $this->db->select("loc_district")->distinct()
					->from("business_locations")
					->join("businesses_has_business_locations", "business_locations.loc_ID = businesses_has_business_locations.loc_ID")->join("users_own_business","businesses_has_business_locations.biz_ID = users_own_business.biz_ID")
					->where("user_ID",$user_ID)->get();
					
			$query2 = $this->db->select("loc_area,loc_district")->distinct()
					->from("business_locations")
					->join("businesses_has_business_locations", "business_locations.loc_ID = businesses_has_business_locations.loc_ID")->join("users_own_business","businesses_has_business_locations.biz_ID = users_own_business.biz_ID")
					->where("user_ID",$user_ID)->get();
			$query3 = $this->db->select("businesses.biz_ID,biz_name,loc_area,business_locations.loc_ID")
					->from("businesses")
					->join("businesses_has_business_locations","businesses.biz_ID = businesses_has_business_locations.biz_ID")
					->join("business_locations","businesses_has_business_locations.loc_ID = business_locations.loc_ID")
					->join("users_own_business","businesses.biz_ID = users_own_business.biz_ID")
					->where("user_ID",$user_ID)
					->get();		
		}
		else{
			$query1 = $this->db->select("loc_district")->distinct()
					->from("business_locations")->get();
			$query2 = $this->db->select("loc_area,loc_district")->distinct()
						->from("business_locations")->get();
			$query3 = $this->db->select("businesses.biz_ID,biz_name,loc_area,business_locations.loc_ID")
					->from("businesses")
					->join("businesses_has_business_locations","businesses.biz_ID = businesses_has_business_locations.biz_ID")
					->join("business_locations","businesses_has_business_locations.loc_ID = business_locations.loc_ID")
					->get();	
		}		
		$result['districts'] = $query1;
		$result['areas'] = $query2;
		$result['businesses'] = $query3;		
		return $result;
	}
	public function authenticate($email,$password){
		$this->db->select('user_ID,first_name,last_name,user_type');
		$this->db->where("email = '" . $email . "' and password = md5('" . $password . "')");
		$query = $this->db->get('users');
		return $query;
	}
	public function add_client($client_details){
		$this->insert_BA_data("users",$client_details);
	}
	public function add_business($biz_details,$user_ID,$us_ID){
		$user_owns_biz= array(
			"user_ID" => $user_ID,
			"biz_ID" => $biz_details["biz_ID"]
		);
		$this->insert_BA_data("businesses",$biz_details);
		$this->insert_BA_data("users_own_business",$user_owns_biz);
		$biz_quota = array(
			"us_ID" => $us_ID,
			"us_state" => "invalid",
			"us_days_left" => 0,
			"us_last_update_date" => date("Y-m-d"),
			"biz_ID" => $biz_details["biz_ID"]
		);
		$this->insert_BA_data("business_usage_quotas",$biz_quota);
	}
	public function add_view($item_ID,$user_ID,$view_type,$loc_ID =""){
		if ($view_type == "business"){
			$view = array(
				"view_date" => date("Y-m-d"),
				"user_ID" => $user_ID,
				"biz_ID" => $item_ID,
				"loc_ID" => $loc_ID
			);
			$this->insert_BA_data("users_view_business",$view);
		}
		else if ($view_type == "product"){
			$view = array(
				"view_date" => date("Y-m-d"),
				"user_ID" => $user_ID,
				"prd_ID" => $item_ID
			);
			$this->insert_BA_data("users_view_products",$view);
		}
	}
	public function del_business($biz_ID){
		$where = array(
					"biz_ID" => $biz_ID
				);
		/*$this->del("businesses_has_business_fields",$where);
		$where2 = array(
					"confirm_status" => "not_confirmed"
				);
		$this->del("business_fields",$where2);
		*/
		$locations = $this->load_locations($biz_ID);
		foreach($locations->result() as $row){
			$this->delete_loc($biz_ID,$row->loc_ID);
			//echo $row->loc_ID . " ";
		}
			//echo "<br><br>";
		$this->del("business_usage_quotas",$where);
		
		$this->del("users_view_business",$where);
		
		$products = $this->load_products($biz_ID);	
		foreach($products->result() as $row){
			$this->delete_prd($biz_ID,$row->prd_ID);
		}	
		
		$this->del("users_own_business",$where);
		$this->del("businesses",$where);			
	}
	public function load_business_info($user_ID, $biz_ID = "all",$search_phrase = ""){
		if ($biz_ID == "all"){
			$this->db->select("businesses.biz_ID,biz_name,biz_slogan,biz_main_field,biz_picture_name");
			$this->db->from("businesses");
			$this->db->join("users_own_business","users_own_business.biz_ID = businesses.biz_ID");
			$this->db->where("users_own_business.user_ID",$user_ID);
			$result = $this->db->get();
			return $result;
		}
		else if ($biz_ID == "search"){
			$this->db->select("businesses.biz_ID,biz_name,biz_slogan,biz_main_field,biz_picture_name");
			$this->db->from("businesses");
			$this->db->join("users_own_business","users_own_business.biz_ID = businesses.biz_ID");
			$this->db->where("users_own_business.user_ID",$user_ID)
					->like("biz_name",$search_phrase);
			$result = $this->db->get();
			return $result;
		}
		else{
			$this->db->select("biz_ID,biz_name,biz_slogan,biz_main_field,biz_main_email,biz_main_mobile,biz_picture_name");
			$this->db->from("businesses");
			$this->db->where("biz_ID",$biz_ID);
			$result = $this->db->get();
			return $result;
		}		
	}
	public function load_business_views($biz_ID){
		$this->db->select('count(*) as num_views_total')->from('users_view_business')->where("biz_ID",$biz_ID);
		$total_views = $this->db->get()->row()->num_views_total;
		$this->db->select('count(*) as num_views_today')->from('users_view_business')->where("biz_ID",$biz_ID)->where("view_date", date("Y-m-d"));
		$today_views = $this->db->get()->row()->num_views_today;
		$result= array(
			"total_views" => $total_views,
			"today_views" => $today_views
		);
		return $result;
	}
	public function load_prdouct_views($biz_ID, $category = "all categories"){
		if($category == "all categories"){
			$this->db->select('cat_name, count(*) as num_views')->from('users_view_products')
				->join("business_products","business_products.prd_ID = users_view_products.prd_ID")
				->join("product_product_category", "business_products.prd_ID = product_product_category.prd_ID")
				->join("product_categories", "product_product_category.cat_ID = product_categories.cat_ID")
				->where("biz_ID",$biz_ID)
				->group_by("cat_name");
			$result = $this->db->get();	
		}
		else{
			$this->db->select('prd_name, count(*) as num_views')->from('users_view_products')
				->join("business_products","business_products.prd_ID = users_view_products.prd_ID")
				->join("product_product_category", "business_products.prd_ID = product_product_category.prd_ID")
				->join("product_categories", "product_product_category.cat_ID = product_categories.cat_ID")
				->where("biz_ID",$biz_ID)
				->where("cat_name",$category)
				->group_by("prd_name");
			$result = $this->db->get();
		}
		return $result;
	}
	public function load_trending_biz($limit = "1000", $offset = "0"){
		$this->db->select("businesses.biz_ID,biz_name, count(*) as num_views")->from("users_view_business")
			->join("businesses","users_view_business.biz_ID = businesses.biz_ID")
			->group_by("biz_name")
			->order_by("num_views","DESC")
			->limit($limit,$offset);
		$result = $this->db->get();
		return $result;
	}
	public function load_new_biz($limit = "1000", $offset = "0"){
		$this->db->select("biz_ID,biz_name, biz_slogan, biz_reg_date")->from("businesses")
				->order_by("biz_reg_date","DESC")
				->limit($limit,$offset);
		$result = $this->db->get();
		$new_biz = array();
		$count = 0;
		$d = strtotime("-1 Month");	 
		foreach ($result->result() as $row){
			if($row->biz_reg_date > date("Y-m-d", $d)){
				$new_biz[$count]['biz_name'] = $row->biz_name;
				$new_biz[$count]['biz_slogan'] = $row->biz_slogan;
				$new_biz[$count]['biz_ID'] = $row->biz_ID;
				$count++;
			}
		}
		return $new_biz;
	}
	public function load_trending_prds($limit = "1000", $offset = "0"){
		$this->db->select("business_products.prd_ID,prd_name,biz_ID,loc_ID,count(*) as num_views")->from("users_view_products")
			->join("business_products","users_view_products.prd_ID = business_products.prd_ID")
			->group_by("users_view_products.prd_ID")
			->order_by("num_views","DESC")
			->limit($limit,$offset);
		$result = $this->db->get();
		return $result;
	}
	public function update_biz_info($biz_ID,$biz_update_details){
		$where = array(
			"biz_ID" => $biz_ID
		);
		$this->update_BA_data("businesses",$biz_update_details,$where);
	}
	public function load_biz_fields($biz_ID){
		$this->db->select()->from("business_fields")
			->join("businesses_has_business_fields","business_fields.field_ID = businesses_has_business_fields.field_ID")
			->where("biz_ID",$biz_ID);
		$result = $this->db->get();
		return $result;
	}
	public function add_field($biz_ID,$field_ID,$field_name){
		$field_details = array(
			"field_ID" => $field_ID,
			"field_name" => $field_name,
			"confirm_status" => "not_confirmed"
		);
		$this->insert_BA_data("business_fields",$field_details);
		
		$field_join = array(
			"field_ID" => $field_ID,
			"biz_ID" => $biz_ID
		);
		$this->insert_BA_data("businesses_has_business_fields",$field_join);
	}
	public function load_products($biz_ID, $prd_select_type = "all", $search_phrase = "", $mod = ""){
		$select = array("business_products.prd_ID","loc_ID","prd_name","prd_price","prd_quantity","product_categories.cat_ID","cat_name","prd_type",
						"prd_condition","prd_description","pic_name");						
		if ($prd_select_type == "all"){
			$this->db->select($select)->from("business_products")
				->join("product_product_category","business_products.prd_ID = product_product_category.prd_ID")
				->join("product_categories","product_product_category.cat_ID = product_categories.cat_ID")
				->join("prd_pictures","business_products.prd_ID = prd_pictures.prd_ID")
				->where("biz_ID", $biz_ID)
				->where("cat_usage","main");
			$result = $this->db->get();
			return $result;	
		}
		else if ($prd_select_type == "search"){
			$this->db->select($select)->from("business_products")
				->join("product_product_category","business_products.prd_ID = product_product_category.prd_ID")
				->join("product_categories","product_product_category.cat_ID = product_categories.cat_ID")
				->join("prd_pictures","business_products.prd_ID = prd_pictures.prd_ID")
				->where("biz_ID", $biz_ID)
				->like("prd_name",$search_phrase)
				->like("loc_ID",$mod)
				->where("cat_usage","main");
			$result = $this->db->get();
			return $result;	
		}
		else if ($prd_select_type == "prd-type-filter"){
			$this->db->select($select)->from("business_products")
				->join("product_product_category","business_products.prd_ID = product_product_category.prd_ID")
				->join("product_categories","product_product_category.cat_ID = product_categories.cat_ID")
				->join("prd_pictures","business_products.prd_ID = prd_pictures.prd_ID")
				->where("biz_ID", $biz_ID)
				->like("prd_type",$search_phrase)
				->like("loc_ID",$mod)
				->where("cat_usage","main");
			$result = $this->db->get();
			return $result;	
		}
		else if ($prd_select_type == "prd-cat-filter"){
			$this->db->select($select)->from("business_products")
				->join("product_product_category","business_products.prd_ID = product_product_category.prd_ID")
				->join("product_categories","product_product_category.cat_ID = product_categories.cat_ID")
				->join("prd_pictures","business_products.prd_ID = prd_pictures.prd_ID")
				->where("biz_ID", $biz_ID)
				->like("cat_name",$search_phrase)
				->like("loc_ID", $mod)
				->where("cat_usage","main");
			$result = $this->db->get();
			return $result;	
		}
		else if ($prd_select_type == "prd-area-filter"){
			$search_phrase = $search_phrase == "not_specified" ? "" : $search_phrase;
			$this->db->select($select)->from("business_products")
				->join("product_product_category","business_products.prd_ID = product_product_category.prd_ID")
				->join("product_categories","product_product_category.cat_ID = product_categories.cat_ID")
				->join("prd_pictures","business_products.prd_ID = prd_pictures.prd_ID")
				->where("biz_ID", $biz_ID)
				->like("loc_ID",$search_phrase)
				->where("cat_usage","main");
			$result = $this->db->get();
			return $result;	
		}
		else{
			$this->db->select($select)->from("business_products")
				->join("product_product_category","business_products.prd_ID = product_product_category.prd_ID")
				->join("product_categories","product_product_category.cat_ID = product_categories.cat_ID")
				->join("prd_pictures","business_products.prd_ID = prd_pictures.prd_ID")
				->where("biz_ID", $biz_ID)
				->where("business_products.prd_ID",$prd_select_type)
				->where("cat_usage","main");
			$result = $this->db->get();
			return $result;	
		}	
	}
	public function load_prd_names($biz_ID){
		$this->db->select("prd_name") 
			->from("business_products")
			->where("biz_ID",$biz_ID);
		$result = $this->db->get();
		return $result;
	}
	public function load_locations($biz_ID, $search_phrase = "all"){
		$select = array("businesses.biz_ID","biz_name","business_locations.loc_ID","loc_area","loc_district","loc_country",
						"loc_description");
		if($search_phrase == "all"){
			$this->db->select($select)->from("business_locations")
				->join("businesses_has_business_locations","business_locations.loc_ID = businesses_has_business_locations.loc_ID")
				->join("businesses","businesses_has_business_locations.biz_ID = businesses.biz_ID")
				->where("businesses.biz_ID", $biz_ID);
			$result = $this->db->get();
			return $result;		
		}
		else if ($search_phrase == "prd-locations"){
			$this->db->select("loc_area,business_locations.loc_ID")->distinct()
				->from("business_locations")
				->join("business_products","business_locations.loc_ID = business_products.loc_ID")
				->where("biz_ID", $biz_ID);
			$result = $this->db->get();
			return $result;
		}
		else{
			$this->db->select($select)->from("business_locations")
				->join("businesses_has_business_locations","business_locations.loc_ID = businesses_has_business_locations.loc_ID")
				->join("businesses","businesses_has_business_locations.biz_ID = businesses.biz_ID")
				->where("businesses.biz_ID", $biz_ID)
				->group_start()
					->like("loc_area", $search_phrase)
					->or_like("loc_district", $search_phrase)
					->or_like("loc_country", $search_phrase)
					->or_like("loc_description", $search_phrase)
				->group_end();
			$result = $this->db->get();
			return $result;
		}			
	}
	public function get_location($loc_ID){
		$result = $this->db->select()->from("business_locations")->where("loc_ID", $loc_ID)->get();
		return $result;
	}
	public function load_prd_categories($biz_ID = "all"){
		if($biz_ID == "all"){
			$this->db->select("*")->from("product_categories")->where("confirm_status","confirmed");	
			$result = $this->db->get();	
		}
		else{
			$this->db->select("cat_name")->distinct()->from("business_products")
			->join("product_product_category","business_products.prd_ID = product_product_category.prd_ID")
			->join("product_categories","product_product_category.cat_ID = product_categories.cat_ID")
			->where("biz_ID",$biz_ID);	
			$result = $this->db->get();	
		}		
		return $result;
	}
	public function load_subscriptions(){
		$this->db->select()->from("subscriptions")->where("subscr_type","usage_quota");
		$result["usage quota"] = $this->db->get();
		$this->db->select()->from("subscriptions")->where("subscr_type","market_boost");
		$result["market boost"] = $this->db->get();
		return $result;
	}
	public function load_mesgs_comms($biz_ID){
		$this->db->select()->from("messages_and_comments")
			->where("biz_ID",$biz_ID);
		$result = $this->db->get();
		return $result;
	}
	public function get_usage_quota($biz_ID){
		$this->db->select()->from("business_usage_quotas")->where("biz_ID",$biz_ID);
		$result = $this->db->get();
		return $result;
	}
	public function get_last_update_date(){
		$this->db->select("us_last_update_date")->distinct()->from("business_usage_quotas");
		$result = $this->db->get();
		return $result;
	}
	public function update_uq($num_of_days){
		$query = "update business_usage_quotas 
				  set us_days_left = us_days_left-" . $num_of_days . ", us_last_update_date = '" . date("Y-m-d") . "'
				  where us_state = 'valid';";
		$this->db->query($query);
		//$where = array("us_state", "valid");
		/*$this->db->set("us_days_left","us_days_left-1");
		$this->db->set("us_last_update_date", date("Y-m-d"));
		$this->db->update("business_usage_quotas");*/
		/*$where = array("us_state", "valid");
		$this->db->set("us_days_left","us_days_left-" . $num_of_days);
		$this->db->set("us_last_update_date",date("Y-m-d"));
		$this->db->where($where);
		$this->db->update("business_usage_quotas");*/		
	}
	public function check_CAT($cat){
		$prd_categories = $this->load_prd_categories();
		foreach ($prd_categories->result() as $row){
			if ($cat == $row->cat_name){
				$cat_details = array(
					"cat_ID" => $row->cat_ID,
					"mod" => "existing_cat"
				);
				return $cat_details;
			}
		}
		$cat_ID = (int)(substr($this->get_last_ID("product_categories","cat_ID"),3));
		$cat_ID = "CAT" . sprintf("%03d",++$cat_ID);
		$cat_details = array(
			"cat_ID" => $cat_ID,
			"cat_name" => $cat,
			"confrim_status" => "not_confirmed",
			"mod" => "new_cat"
		);
		return $cat_details;
	}
	public function get_CAT($cat_ID){
		$prd_categories = $this->load_prd_categories();
		foreach ($prd_categories->result() as $row){
			if ($cat_ID == $row->cat_ID){
				$cat_name = $row->cat_name;
				return $cat_name;
			}
		}
		return $cat_ID;
	}
	public function add_products($prd_details,$prd_category,$prd_pic,$single_upload = true){
		//update products
		$this->insert_BA_data("business_products",$prd_details);
		
		
		//update categories
		if ($single_upload){
			$category = array(
				"prd_ID" => $prd_details["prd_ID"],
				"cat_ID" => $prd_category,
				"cat_usage" => "main"
			);
			$this->insert_BA_data("product_product_category",$category);
		}
		else{
			$cat_details = $this->check_CAT($prd_category);
			if ($cat_details["mod"] == "existing_cat"){
				$category = array(
					"prd_ID" => $prd_details["prd_ID"],
					"cat_ID" => $cat_details["cat_ID"],
					"cat_usage" => "main"
				);
				$this->insert_BA_data("product_product_category",$category);
			}
			else if ($cat_details["mod"] == "new_cat"){
				$category_add = array(
					"cat_ID" => $cat_details["cat_ID"],
					"cat_name" => $cat_details["cat_name"],
					"confirm_status" => $cat_details["confrim_status"]
				);
				$this->insert_BA_data("product_categories",$category_add);
				$category = array(
					"prd_ID" => $prd_details["prd_ID"],
					"cat_ID" => $cat_details["cat_ID"],
					"cat_usage" => "main"
				);
				$this->insert_BA_data("product_product_category",$category);				
			}
		}
		
		
		//update picture	
		$pic_details = array(
			"prd_ID" => $prd_details["prd_ID"],
			"pic_name" => $prd_pic,
			"usage" => "thumbnail"
		);
		$this->insert_BA_data("prd_pictures",$pic_details);
	}
	public function add_location($biz_ID,$loc_details){
		//update locations
		$this->insert_BA_data("business_locations",$loc_details);
		
		//update relationship table
		$rel_table = array(
			"biz_ID" => $biz_ID,
			"loc_ID" => $loc_details['loc_ID']
		);		
		$this->insert_BA_data("businesses_has_business_locations",$rel_table);
	}
	public function edit_product($prd_ID,$prd_details,$prd_category,$prd_pic){
		$where = array ("prd_ID" => $prd_ID);		
		
		//update products		
		$this->update_BA_data("business_products",$prd_details,$where);		
		
		//update categories
		$category_name = $this->get_CAT($prd_category);
		$cat_details = $this->check_CAT($category_name);
		if ($cat_details["mod"] == "existing_cat"){
			$category = array(
				"cat_ID" => $cat_details["cat_ID"],
			);
			$this->update_BA_data("product_product_category",$category,$where);
		}
		else if ($cat_details["mod"] == "new_cat"){
			$category_add = array(
				"cat_ID" => $cat_details["cat_ID"],
				"cat_name" => $cat_details["cat_name"],
				"confirm_status" => $cat_details["confrim_status"]
			);
			$this->insert_BA_data("product_categories",$category_add);
			$category = array(
				"cat_ID" => $cat_details["cat_ID"],
			);
			$this->update_BA_data("product_product_category",$category,$where);				
		}		
		
		
		//update picture	
		$pic_details = array(
			"pic_name" => $prd_pic,
		);
		$this->update_BA_data("prd_pictures",$pic_details,$where);
	}
	public function edit_loc($loc_details){
		$where = array("loc_ID" => $loc_details["loc_ID"]);
		$this->update_BA_data("business_locations",$loc_details,$where);
	}
	public function delete_prd($biz_ID,$prd_ID){
		$where = array("prd_ID" => $prd_ID);
				
		//del views
		$this->del("users_view_products",$where);
		
		//del prd picture. first delete record then delete assoicated picture stored on server
		$pic_name = $this->load_products($biz_ID,$prd_ID)->row()->pic_name;
		$this->del("prd_pictures",$where);	
		if ($pic_name != "no_image_thumb.gif"){
			$pic_name = "C:/wamp64/www/Business_arena/images/uploads/" . $pic_name;
			unlink($pic_name);
		}	
		
		//del prd category
		$this->del("product_product_category",$where);
		
		//del views
		$this->del("users_view_products",$where);
		
		//del prd
		$this->del("business_products",$where);		
	}
	public function delete_loc($biz_ID,$loc_ID){		
		$where = array("loc_ID" => $loc_ID,"biz_ID" => $biz_ID);		
		//del loc relationship
		$this->del("businesses_has_business_locations",$where);	
		
		$where = array("loc_ID" => $loc_ID);		
		//del loc
		$this->del("business_locations",$where);			
	}
	public function insert_BA_data($table,$data){
		$this->db->insert($table,$data);
	}
	public function update_BA_data($table,$data,$where){
		$this->db->where($where);
		$this->db->update($table,$data);
	}
	public function del($tableName,$where){
		$this->db->delete($tableName,$where);
	}
	public function get_last_ID($table,$column){
		$this->db->select($column)->from($table)->order_by($column,"desc");
		$last_record = $this->db->get();
		if ($last_record->num_rows() == 0){
			$result = 0;				
		}
		else{
			$result = $last_record->row()->$column;
		}		
		return $result;		
	}
	public function prd_search($search_items, $mod){
		$select = array("prd_ID","biz_ID","loc_ID","prd_name");
		if ($mod == "strict"){
			$this->db->select($select)->from("business_products");
			foreach ($search_items as $item){
				$this->db->like("prd_name",$item);
			}	
			$result = $this->db->get();
			return $result;
		}	
		else if ($mod == "close"){
			$this->db->select($select)->from("business_products");
			$counter = 0;
			foreach ($search_items as $item){
				if ($counter == 0){
					$this->db->like("prd_name",$item);	
				}
				else{
					$this->db->or_like("prd_name", $item);
				}
				$counter++;
			}	
			$result = $this->db->get();
			return $result;
		}	
	}
	public function biz_search($search_items, $mod){
		if ($mod == "strict"){
			$this->db->select("biz_ID, biz_name")->from("businesses");
			foreach ($search_items as $item){
				$this->db->like("biz_name",$item);
			}	
			$result = $this->db->get();
			return $result;
		}	
		else if ($mod == "close"){
			$this->db->select("biz_ID, biz_name")->from("businesses");
			$counter = 0;
			foreach ($search_items as $item){
				if ($counter == 0){
					$this->db->like("biz_name",$item);	
				}
				else{
					$this->db->or_like("biz_name", $item);
				}
				$counter++;
			}	
			$result = $this->db->get();
			return $result;
		}	
	}
	public function loc_search($search_items, $mod){
		if ($mod == "strict"){
			$this->db->select("loc_ID, loc_area")->from("business_locations");
			foreach ($search_items as $item){
				$this->db->group_start();
					$this->db->like("loc_area",$item);
					$this->db->or_like("loc_district",$item);
					$this->db->or_like("loc_country",$item);
					$this->db->or_like("loc_description",$item);
				$this->db->group_end();			
			}	
			$result = $this->db->get();
			return $result;
		}	
		else if ($mod == "close"){
			$this->db->select("loc_ID, loc_area")->from("business_locations");
			$counter = 0;
			foreach ($search_items as $item){
				if ($counter == 0){
					$this->db->group_start();
						$this->db->like("loc_area",$item);
						$this->db->or_like("loc_district",$item);
						$this->db->or_like("loc_country",$item);
						$this->db->or_like("loc_description",$item);
					$this->db->group_end();
				}
				else{
					$this->db->or_group_start();
						$this->db->like("loc_area",$item);
						$this->db->or_like("loc_district",$item);
						$this->db->or_like("loc_country",$item);
						$this->db->or_like("loc_description",$item);
					$this->db->group_end();
				}
				$counter++;
			}	
			$result = $this->db->get();
			return $result;
		}	
	}
/*	public function generateCatalogue($userType){
		if ($userType == "client"){
			$query1 = $this->db->query('SELECT distinct locDistrict 
								FROM users, users_have_biz_description,biz_description, biz_description_biz_location, biz_location
								where users.userID = users_have_biz_description.userID and 
									  users_have_biz_description.bizID=biz_description.bizID and 
									  biz_description.bizID = biz_description_biz_location.bizID and 
			                          biz_description_biz_location.locID = biz_location.locID and users.userID = "' . $this->session->userdata('userID') . '";');
			$query2 = $this->db->query('SELECT distinct locArea, locDistrict 
								FROM users, users_have_biz_description,biz_description, biz_description_biz_location, biz_location
								where users.userID = users_have_biz_description.userID and 
									  users_have_biz_description.bizID=biz_description.bizID and 
									  biz_description.bizID = biz_description_biz_location.bizID and 
			                          biz_description_biz_location.locID = biz_location.locID and users.userID = "' . $this->session->userdata('userID') . '";');
			$query3 = $this->db->query('SELECT biz_description.bizID,bizName,locArea,biz_location.locID
								FROM users, users_have_biz_description,biz_description, biz_description_biz_location, biz_location
								where users.userID = users_have_biz_description.userID and 
									  users_have_biz_description.bizID=biz_description.bizID and 
									  biz_description.bizID = biz_description_biz_location.bizID and 
			                          biz_description_biz_location.locID = biz_location.locID and users.userID = "' . $this->session->userdata('userID') . '";');
		}
		else{		
			$query1 = $this->db->query('SELECT distinct locDistrict 
								FROM biz_description, biz_description_biz_location, biz_location
								where biz_description.bizID = biz_description_biz_location.bizID and biz_description_biz_location.locID = biz_location.locID;');
			$query2 = $this->db->query('SELECT distinct locArea, locDistrict 
								FROM biz_description, biz_description_biz_location, biz_location
								where biz_description.bizID = biz_description_biz_location.bizID and biz_description_biz_location.locID = biz_location.locID;');
			$query3 = $this->db->query('SELECT biz_description.bizID,bizName,locArea, biz_location.locID
								FROM biz_description, biz_description_biz_location, biz_location
								where biz_description.bizID = biz_description_biz_location.bizID and biz_description_biz_location.locID = biz_location.locID;');
		}
			
		
		foreach ($query1->result() as $row)
		{
			echo $row->locDistrict . "<br>";
		}
		echo "<br>";
		foreach ($query2->result() as $row)
		{
			echo $row->LocArea . "<br>";
		}
		echo "<br>";
		foreach ($query3->result() as $row)
		{
			echo $row->bizName . "<br>";
		}
		
		$query[0] = $query1;
		$query[1] = $query2;
		$query[2] = $query3;
		return $query;
	}
	public function customQuery($query){		
		$output = $this->db->query($query);
		return $output;		
	}
*/
}
?>