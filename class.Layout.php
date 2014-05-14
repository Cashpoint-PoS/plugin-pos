<?
class KS_Layout extends DBObj {
  protected static $__table="ks_layouts";
  public static $mod="ks";
  public static $sub="layouts";
  
  public static $elements=array(
    "label"=>array("title"=>"Beschriftung","mode"=>"string","dbkey"=>"label"),
  );
  
  public static $link_elements=array(
  );
  public static $list_elements=array(
    "label",
  );
  public static $detail_elements=array(
    "label",
  );
  public static $edit_elements=array(
    "label",
  );
  public static $links=array(
    "KS_Button"=>array("title"=>"Buttons","table"=>"link_ks_layout_buttons"),
  );
  public function processProperty($key) {
    $ret=NULL;
    switch($key) {
    }
    return $ret;
  }
}

plugins_register_backend_handler($plugin,"layouts","list",array("KS_Layout","listView"));
plugins_register_backend_handler($plugin,"layouts","edit",array("KS_Layout","editView"));
plugins_register_backend_handler($plugin,"layouts","view",array("KS_Layout","detailView"));
plugins_register_backend_handler($plugin,"layouts","submit",array("KS_Layout","processSubmit"));
