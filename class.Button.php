<?
class KS_Button extends DBObj {
  protected static $__table="ks_buttons";
  public static $mod="ks";
  public static $sub="buttons";
  
  public static $elements=array(
    "action"=>array("title"=>"Aktion (JS)","mode"=>"string","dbkey"=>"action"),
    "label"=>array("title"=>"Beschriftung","mode"=>"string","dbkey"=>"label"),
    "size"=>array("title"=>"Format","mode"=>"string","dbkey"=>"size"),
  );
  
  public static $link_elements=array(
  );
  public static $list_elements=array(
    "action",
    "label",
    "size",
  );
  public static $detail_elements=array(
    "action",
    "label",
    "size",
  );
  public static $edit_elements=array(
    "action",
    "label",
    "size",
  );
  public static $links=array(
    "KS_Layout"=>array("title"=>"Layouts","table"=>"link_ks_layout_buttons"),
  );
  public function processProperty($key) {
    $ret=NULL;
    switch($key) {
    }
    return $ret;
  }
}

plugins_register_backend_handler($plugin,"buttons","list",array("KS_Button","listView"));
plugins_register_backend_handler($plugin,"buttons","edit",array("KS_Button","editView"));
plugins_register_backend_handler($plugin,"buttons","view",array("KS_Button","detailView"));
plugins_register_backend_handler($plugin,"buttons","submit",array("KS_Button","processSubmit"));
