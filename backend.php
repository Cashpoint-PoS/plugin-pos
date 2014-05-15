<?
plugins_register_backend($plugin,array("icon"=>"icon-folder-stroke","sub"=>array(
  "buttons"=>"Buttons",
  "layouts"=>"Layouts",
)));

require("class.Button.php");
require("class.Layout.php");

//JSON only callback
function KS_addToInvoice() {
  if(!isset($_GET["inv_id"]))
    throw new Exception("inv_id missing");
  if(!isset($_GET["ww_aid"]))
    throw new Exception("ww_aid missing");
  if(!isset($_GET["amount"]))
    throw new Exception("amount missing");
  $inv_id=(int)$_GET["inv_id"];
  if($inv_id==0)
    throw new Exception("inv_id zero");
  $ww_aid=(int)$_GET["ww_aid"];
  $amount=(int)$_GET["amount"];
  $inv_obj=Inv_Invoice::getById($inv_id);
  $art_obj=Wawi_Article::getById($ww_aid);
  $prices=Wawi_Price::getByOwner($art_obj);
  if(sizeof($prices)==0)
    throw new Exception("no prices found");
  $applicable_price=null;
  foreach($prices as $p) {
    if($amount<$p->min_quant)
      continue;
    if($applicable_price==null) {
      $applicable_price=$p;
      continue;
    }
    if($p->min_quant>$applicable_price->min_quant) {
      $applicable_price=$p;
      continue;
    }
  }
  if($applicable_price==null)
    throw new Exception("no matching price found");
  $ip=Inv_Position::fromScratch();
  $ip->inv_invoices_id=$inv_obj->id;
  $ip->ts=time();
  $ip->sku=$art_obj->sku;
  $ip->price=$applicable_price->price;
  $ip->amount=$amount;
  $ip->vat_percentage=$applicable_price->vat_percentage;
  $ip->shortdesc=$art_obj->shortdesc;
  $ip->longdesc=$art_obj->longdesc;
  $positions=Inv_Position::getByOwner($inv_obj,false);
  $ip->inv_order=sizeof($positions)+1;
  $ip->commit();
}

//JSON only callback
function KS_removeFromInvoice() {
  if(!isset($_GET["invpos_id"]))
    throw new Exception("invpos_id missing");
  $invpos_id=(int)$_GET["invpos_id"];
  $invpos_obj=Inv_Position::getById($invpos_id);
  $inv_obj=Inv_Invoice::getById($invpos_obj->inv_invoices_id);

  $ip=Inv_Position::fromScratch();
  $ip->inv_invoices_id=$invpos_obj->inv_invoices_id;
  $ip->ts=time();
  $ip->sku=$invpos_obj->sku;
  $ip->price=$invpos_obj->price;
  $ip->amount=0-$invpos_obj->amount;
  $ip->vat_percentage=$invpos_obj->vat_percentage;
  $ip->shortdesc="STORNO #".$invpos_obj->inv_order;
  $positions=Inv_Position::getByOwner($inv_obj,false);
  $ip->inv_order=sizeof($positions)+1;
  $ip->commit();
}

//JSON only callback
function KS_getOpenInvoices() {
  $ret=array();
  $list=Inv_Invoice::getByFilter("where bill_state=0");
  DBObj_Interface_JSON::listView("Inv_Invoice",$list);
}

////JSON only callback
function KS_openNewInvoice() {
  $inv_obj=Inv_Invoice::fromScratch();
  $inv_obj->commit();
  DBObj_Interface_JSON::detailView($inv_obj);
}

function KS_registerPayment() {
  if(!isset($_GET["inv_id"]))
    throw new Exception("inv_id missing");
  if(!isset($_GET["amount"]))
    throw new Exception("amount missing");
  if(!isset($_GET["type"]))
    throw new Exception("type missing");
  
  $inv_id=(int)$_GET["inv_id"];
  if($inv_id==0)
    throw new Exception("inv_id zero");
  $amount=(int)$_GET["amount"];
  if($amount==0)
    return;
  $type=(int)$_GET["type"];
  $inv_obj=Inv_Invoice::getById($inv_id);
  if($inv_obj->bill_state!=0)
    throw new Exception("bill is locked");
  $pay_obj=Inv_Payment::fromScratch();
  $pay_obj->inv_invoices_id=$inv_obj->id;
  $pay_obj->ts=time();
  $pay_obj->amount=$amount;
  $pay_obj->type=$type;
  $pay_obj->commit();
  $due=$inv_obj->processProperty("payments_due");
  if($due==0) { //matching payment
    $inv_obj->payment_state=2; //fully paid
    $inv_obj->bill_state=1; //locked
  } elseif($due<0) { //overpayment with change
    $inv_obj->payment_state=2; //fully paid
    $inv_obj->bill_state=1; //locked
  } else { //not everything's been paid
    $inv_obj->payment_state=1;
  }
  $inv_obj->commit();
}

plugins_register_backend_handler($plugin,"transactions","addtoinvoice","KS_addToInvoice");
plugins_register_backend_handler($plugin,"transactions","removefrominvoice","KS_removeFromInvoice");

plugins_register_backend_handler($plugin,"transactions","getopeninvoices","KS_getOpenInvoices");
plugins_register_backend_handler($plugin,"transactions","opennewinvoice","KS_openNewInvoice");
plugins_register_backend_handler($plugin,"transactions","registerpayment","KS_registerPayment");

plugins_register_target($plugin,"app/index.php","Kassensystem");
