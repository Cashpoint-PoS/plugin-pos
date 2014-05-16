<?
require("../../../lib.php");
//check if we're logged in
if(!isset($_SESSION["user"]))
  redir("../../../index.php");

?>
<!doctype html>
<html>
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="initial-scale=1,maximum-scale=1,user-scalable=0,width=320.1" />
    <meta name="google" value="notranslate" />
    <title>Kassensystem</title>
    <script type="text/javascript" src="<?=$config["paths"]["webroot"]?>/shared-js/console.js"></script>
    <script type="text/javascript" src="<?=$config["paths"]["webroot"]?>/shared-js/api.js"></script>
    <script type="text/javascript" src="<?=$config["paths"]["webroot"]?>/shared-js/jquery-2.1.0.js"></script>
    <script type="text/javascript" src="<?=$config["paths"]["webroot"]?>/shared-js/jquery.ba-hashchange.js"></script>
    <script type="text/javascript" src="<?=$config["paths"]["webroot"]?>/shared-js/sprintf.js"></script>
    <script type="text/javascript" src="<?=$config["paths"]["webroot"]?>/shared-js/date.format.js"></script>
    <script type="text/javascript" src="js/bililiteRange.js"></script>
    <script type="text/javascript" src="js/jquery.sendkeys.js"></script>
    <script type="text/javascript" src="<?=$config["paths"]["webroot"]?>/shared-js/i18n.js"></script>
    <script type="text/javascript" src="i18n/de.js"></script>
    
    <script type="text/javascript">
var appconfig={
  apiurl:"<?=$config["paths"]["api"]?>",
  webroot:"<?=$config["paths"]["webroot"]?>",
  localurl:"http://localhost/ks_services/api.php",
  deflang:"de",
};
if(typeof appstate!="object")
  appstate={};

$(document).ready(function() {
  appstate.index={};
  appstate.index.active_layout=0;
  appstate.index.layouts=[];
  appstate.index.active_bill=0;
  appstate.index.bills={};
  appstate.index.billtimer=0;
  doAPIRequest("list",{mod:"ks",sub:"layouts"},function(data) {
    var $l=$("#index-layout").empty();
    if(data.data.length==0)
      data.data.push({_raw:{id:0,label:"Kein Layout"},_links:{KS_Button:[]}});
    console.glog("index_loadlayouts","got data",data);
    appstate.index.layouts=[];

    data.data.forEach(function(e) {
      console.glog("index_loadlayouts","adding layout",e);
      appstate.index.layouts[e._raw.id]=e._links.KS_Button;
      $("<option>").html(e._raw.label).attr("value",e._raw.id).appendTo($l);
    });
    $l.change();
    $("#index-rightpane-scannerentry").focus();
  });
  var _oldbase=appconfig.apiurl;
  appconfig.apiurl=appconfig.localurl;
  appstate.terminal={};
  doAPIRequest("terminal_details",{ignoreNetworkException:true},function(data) {
    data=data.data;
    appstate.terminal=data;
    $("#header-tid").html(data.name);
  },function() {
//    alert("not on a terminal");
  });
  appconfig.apiurl=_oldbase;
  $l=$("#index-layout");
  $l.change(function() {
    var v=$l.val();
    if(!appstate.index.layouts[v]) {
      alert("unknown layout "+v);
      return;
    }
    var l=appstate.index.layouts[v];
    var $c=$("#index-rightpane-buttons").empty();
    console.glog("view.index_layout","loading layout",v,l);
    l.forEach(function(b) {
      console.glog("view.index_layout","adding button",b);
      var $b=$("<button>").addClass("button").appendTo($c);
      $b.attr("onclick",b.action);
      $b.addClass("size-"+b.size);
      $b.html(b.label);
    });
  });
  $("#index-opentabs").change(function() {
    console.glog("index-opentabs.onChange","from",appstate.index.active_bill,"to",$("#index-opentabs").val());
    if(appstate.index.active_bill==$("#index-opentabs").val()) {
      index_showbilldata(appstate.index.active_bill);
      return;
    }
    appstate.index.active_bill=$("#index-opentabs").val();
    if(appstate.index.active_bill==0){
      $("#index-opentab-billname").html("(keine Rechnung offen)");
      $("#index-leftpane-billdata").hide();
      return;
    }
    index_showbilldata(appstate.index.active_bill);
  });
  index_updatebills();
  $("#header-index").click(function() {
    location.hash="index";
  });
});
function index_switchBill(billid) {
  $("#index-opentabs").val(billid).change();
}
function index_killInactiveBills(data) {
  var toBeRemoved=[];
  console.glog("index_killInactiveBills","checking",appstate.index.bills,"against new",data);
  for(var e in appstate.index.bills) {
    e=appstate.index.bills[e];
//    console.glog("index_killInactiveBills","checking bill element",e,e._raw.id);
    var found=false;
    data.forEach(function(e2) {
//      console.glog("index_killInactiveBills","checking sub element",e2,e2._raw.id);
      if(e2._raw.id==e._raw.id)
        found=true;
    });
    if(found==false) {
//      console.glog("index_killInactiveBills","pushing element",e._raw.id);
      toBeRemoved.push(e._raw.id);
    }
  }
  console.glog("index_killInactiveBills","removing:",toBeRemoved);
  toBeRemoved.forEach(function(e) {
    if(appstate.index.bills[e])
      delete appstate.index.bills[e];
    $o=$("#index-opentabs-"+e);
    if($o.length!=0)
      $o.remove();
  });
  if(!appstate.index.bills[appstate.index.active_bill]) {
    $("#index-opentabs").val(0).change();
  }
}
function index_updatebills() {
  clearTimeout(appstate.index.billtimer);
  console.glog("view.index_billtimer","updating bill list");
  doAPIRequest("getopeninvoices",{mod:"ks",sub:"transactions"},function(data) {
    data=data.data;
    console.glog("view.index_billtimer","got bill list",data);
    data.forEach(index_insertBill);
    console.glog("view.index_billtimer","bills inserted");
    index_killInactiveBills(data);
    if($("#index-opentabs").val()!=appstate.index.active_bill) {
      console.glog("view.index_billtimer","firing change event");
      $("#index-opentabs").val(appstate.index.active_bill).change();
    } else {
      console.glog("view.index_billtimer","running showbilldata");
      index_showbilldata(appstate.index.active_bill);
    }
    appstate.index.billtimer=setTimeout(index_updatebills,1000*30);
    $("#modal-lock,#modal-container").hide();
    if(appstate.view=="index")
      $("#index-rightpane-scannerentry").focus();
  });
}
function index_showbilldata(billid) {
  if(billid==0)
    return;
  var billdata=appstate.index.bills[billid];
  console.glog("index_showbilldata","showing bill",billid,billdata);
  $("#index-opentab-billname").html(billdata._raw.external_id);
  $("#index-leftpane-billdata").show();
  var $t=$("#index-leftpane-billpos tbody");
  $t.empty();
  billdata._o2m.Inv_Position.elements.forEach(function(e) {
    var $r=$("<tr>").appendTo($t);
    $("<td>").html(e._raw.inv_order).appendTo($r);
    $("<td>").html(e._elements.ts_fmt).appendTo($r);
    $("<td>").html(e._elements.amount+"x "+e._elements.shortdesc).appendTo($r);
    $("<td>").html(e._elements.price_total_vat_fmt+" "+e._elements.currency).appendTo($r);
    $("<td>").html("").appendTo($r);
  });
  $("#index-leftpane-billtotal").html(billdata._elements.total_vat_fmt);
  $("#index-leftpane-billalreadypaid").html(billdata._elements.payments_done_fmt);
  $("#index-leftpane-billdue").html(billdata._elements.payments_due_fmt);
}
function index_insertBill(e) {
  console.glog("index_insertBill","inserting bill id",e._raw.id,"data",e);
  appstate.index.bills[e._raw.id]=e;
  $o=$("#index-opentabs-"+e._raw.id);
  if($o.length==0) {
    $o=$("<option>");
    $o.attr("id","index-opentabs-"+e._raw.id);
    $o.html(e._raw.external_id+" ("+e._raw.create_time+")");
    $o.attr("value",e._raw.id);
    $o.appendTo($("#index-opentabs"));
  }
  
}
//get the current active bill's id - if none's active, create one
function index_getCurrentBillId() {
  if(appstate.index.active_bill==0) {
    doAPIRequest("opennewinvoice",{mod:"ks",sub:"transactions",sync:true},function(data) {
      data=data.data;
      console.glog("index_getCurrentBillId","returned data",data);
      appstate.index.bills[data._raw.id]=data;
      //load the new bill into the system
      index_insertBill(data);
      $("#index-opentabs").val(data._raw.id).change();
    });
  }
  return appstate.index.active_bill;
}
function index_addToCurrentBill(art_id) {
  clearTimeout(appstate.index.billtimer);
  $(document).trigger("poscash_modal_show","#index-modal-amount");
  $("#index-modal-amount-amount").val(1).focus().get(0).select();
  appstate.index.current_art_id=art_id;
}
$(document).ready(function() {
  $("#index-modal-amount-cancel").click(function(e) {
    e.preventDefault();
    $("#modal-container,#index-modal-amount").hide();
    index_updatebills();
  });
  $("#index-modal-payment-cash-cancel").click(function(e) {
    e.preventDefault();
    $("#modal-container,#index-modal-payment-cash").hide();
    index_updatebills();
  });
  $("#index-modal-payment-cash-amount-euro,#index-modal-payment-cash-amount-cent").change(function() {
    var billId=index_getCurrentBillId();
    var amountEuro=parseInt($("#index-modal-payment-cash-amount-euro").val());
    var amountCent=parseInt($("#index-modal-payment-cash-amount-cent").val());
    var amount=(amountEuro*100)+amountCent;
    var billdata=appstate.index.bills[billId];
    var amount_due=parseInt(billdata._elements.payments_due);
    var change=amount-amount_due;
    if(change>0) {
      $("#index-modal-payment-cash-changelabel").html("Rückgeld");
    } else {
      $("#index-modal-payment-cash-changelabel").html("Noch offen");
    }
    $("#index-modal-payment-cash-changevalue").html(sprintf("%0.2f",Math.abs(change)/100));
  });
});

function index_continueArticleAdd() {
  $("#modal-container,#index-modal-amount").hide();
  var billId=index_getCurrentBillId();
  var artid=appstate.index.current_art_id;
  var amount=$("#index-modal-amount-amount").val();
  console.glog("index_continueArticleAdd","adding",artid,"to",billId);
  doAPIRequest("addtoinvoice",{mod:"ks",sub:"transactions",inv_id:billId,ww_aid:artid,amount:amount},null,null,function(data) {
    index_updatebills();
  });  
  return false;
}
$(document).ready(function() {
  $("#index-modal-payment-cash button.paybill").click(function() {
    var v=parseInt($("#index-modal-payment-cash-amount-euro").val());
    var a=parseInt($(this).data("amount"));
    v+=a;
    $("#index-modal-payment-cash-amount-euro").val(v).change();
  });
});
function index_payCash() {
  if(appstate.index.active_bill==0)
    return;
  clearTimeout(appstate.index.billtimer);
  $(document).trigger("poscash_modal_show","#index-modal-payment-cash");
  $("#index-modal-payment-cash-amount-euro,#index-modal-payment-cash-amount-cent").val(0);
  $("#index-modal-payment-cash-amount-euro").change().focus();
}
function index_continuePaymentCash(printBill) {
  $("#modal-container,#index-modal-payment-cash").hide();
  $(document).trigger("poscash_modal_show","#modal-lock");
  var billId=index_getCurrentBillId();
  var amountEuro=parseInt($("#index-modal-payment-cash-amount-euro").val());
  var amountCent=parseInt($("#index-modal-payment-cash-amount-cent").val());
  var amount=(amountEuro*100)+amountCent;
  console.glog("index_continuePaymentCash","paying",amount,"on bill",billId);
  doAPIRequest("registerpayment",{mod:"ks",sub:"transactions",inv_id:billId,type:0,amount:amount,printBill:printBill},function(data) {
    index_updatebills();
  });
  return false;
}
function index_paymentCash_setMatching() {
  var billId=index_getCurrentBillId();
  var billdata=appstate.index.bills[billId];
  var amount_due=parseInt(billdata._elements.payments_due);
  var dueEuro=Math.floor(amount_due/100);
  var dueCent=amount_due%100;
  $("#index-modal-payment-cash-amount-euro").val(dueEuro);
  $("#index-modal-payment-cash-amount-cent").val(dueCent).change();
  
}
$(document).on("cashpoint_view_index",function() {
  $("#view-index").show();
  $("#index-rightpane-scannerentry").focus();
});
$(document).ready(function() {
  doAPIRequest("getsessiondata",{},function(data) {
    $("#header-username").html(data.data.displayname);
  });
  $("#header-logout").click(function() {
    location.href=appconfig.webroot+"/logout.php";
  });
  $("#index-rightpane-scannerentry").focus().keyup(function(e) {
    if(e.keyCode!=13) //return
      return;
    e.preventDefault();
    e.stopPropagation();
    $("#index-rightpane-scannersubmit").click();
  });
  $("#index-rightpane-scannersubmit").click(function() {
    var $b=$("#index-rightpane-scannerentry");
    var v=$b.val();
    if(v=="")
      return;
    $b.val("");
    doAPIRequest("findbybarcode",{mod:"wawi",sub:"transactions",barcode:v},function(data) {
      if(data.data.length==0) {
        alert("Kein Artikel gefunden");
        return;
      } else if(data.data.length>1) {
        alert("Fehler: mehrere Artikel für diesen Barcode gefunden");
        return;
      }
      $(document).trigger("poscash_modal_show","#modal-lock");
      var billId=index_getCurrentBillId();
      var artid=data.data[0]._raw.id;
      console.glog("index_findbybarcode","adding",artid,"to",billId);
      doAPIRequest("addtoinvoice",{mod:"ks",sub:"transactions",inv_id:billId,ww_aid:artid,amount:1},null,null,function(data) {
        index_updatebills();
      });
    });
    $("#index-rightpane-scannerentry").focus();
    console.glog("submitted",v);
  });
});
jQuery(document).ready(function($){
  appstate.modal={};
  appstate.modal.inputhelp_active_element=null;
  $("#index-modal-inputhelp .numpadbtn").each(function() {
    var $e=$(this);
    var v=$e.html();
    $e.empty();
    $b=$("<button>").appendTo($e).html(v);
    switch(v) {
      case "&#8592;":
      case "←":
        $b.click(function() {
          var $el=appstate.modal.inputhelp_active_element;
          if($el==null)
            return;
          $el.val("").focus().change();
        });
      break;
      default:
        $b.click(function() {
          var $el=appstate.modal.inputhelp_active_element;
          if($el==null)
            return;
          var ot=$el.attr("type");
          $el.attr("type","text"); //chrome fucks up, when type=number the selection methods dont work
          var ss=$el.get(0).selectionStart;
          var se=$el.get(0).selectionEnd;
          if(ss!=se)
            $el.val("");
          $el.attr("type",ot);
          $el.val($el.val()+""+v).focus().change();
        });
    }
  });
  $('.modal-box input[type="text"],.modal-box input[type="number"]').focusin(function() {
    console.glog("focusin","got focusin on",$(this));
    appstate.modal.inputhelp_active_element=$(this);
    $("#index-modal-inputhelp").show();
  }).focusout(function(e) {
    console.glog("focusout","got focusout on",$(this),"target is",$(e.relatedTarget),"ed",e);
    if($(e.relatedTarget).parent().hasClass("numpadbtn"))
      return;
    appstate.modal.inputhelp_active_element=null;
    $("#index-modal-inputhelp").hide();
  }).keypress(function(e) {
    console.glog("keypress","got keypress evd",e,$(this));
  });
  $(".modal-close").click(function() {
    console.glog("view.modal","detected click on modal-close button");
    $("#modal-container,.modal-box").hide(); //hide the overlay
//    $(this).parent().hide(); //and hide the container the button belongs to
  });
  //handle escape-key-presses
  $(window).keydown(function(e) {
    if(e.keyCode!=27) //escape
      return;
    
    if($(".modal-close").filter(":visible").length==0) //check if the current modal is active
      return;
    console.glog("view.modal","detected esc-key press");
    $(".modal-close").filter(":visible").click();
  });
});
$(document).on("poscash_modal_show",function(e,a) {
  console.glog("view.modal","showing modal with selector",a);
  if($("#modal-container").is(":visible")) {
    console.gerror("view.modal","container already visible!");
    return;
  }
  $("#modal-container,"+a).show();
//  debugger;
});
//admin
$(document).ready(function() {
  $("#header-admin").click(function() {
    location.hash="admin/index";
  });
});
$(document).on("cashpoint_view_admin",function(a,b) {
  var subview=b.args;
  if(!subview) {
    location.hash="admin/index";
    return;
  }
  
  var v=/^\#([^\/\s]+)(?:\/([^\s]*))?$/.exec("#"+subview);
  if(!v) {
    console.gerror("cashpoint_view_admin","invalid hash",subview);
    return;
  }
  console.glog("cashpoint_view_admin","opening subview",v[1],"with args",v[2]);
  $("#view-admin").show();
  $("#admin-rightpane .subview").hide();
  $("#admin-subview-"+v[1]).show();
  $(document).trigger("cashpoint_view_admin_"+v[1],{args:v[2]});
});
$(document).on("cashpoint_view_admin_layout",function() {
  var $c=$("#admin-subview-layout-layout-list tbody").empty();
  doAPIRequest("list",{mod:"ks",sub:"layouts"},function(data) {
    data=data.data;
    data.forEach(function(e) {
      $r=$("<tr>").appendTo($c);
      $("<td>").html(e._raw.id).appendTo($r);
      $("<td>").html(e._raw.label).appendTo($r);
      var $atd=$("<td>").appendTo($r);
      $("<button>").appendTo($atd).html("Anzeige").click(function() {
        location.hash="admin/layout_detail/"+e._raw.id;
      });
    });
  });
  var $c2=$("#admin-subview-layout-button-list tbody").empty();
  doAPIRequest("list",{mod:"ks",sub:"buttons"},function(data) {
    data=data.data;
    data.forEach(function(e) {
      $r=$("<tr>").appendTo($c2);
      $("<td>").html(e._raw.id).appendTo($r);
      $("<td>").html(e._raw.label).appendTo($r);
      $("<td>").html(e._raw.action).appendTo($r);
      var $atd=$("<td>").appendTo($r);
      $("<button>").appendTo($atd).html("Anzeige").click(function() {
        location.hash="admin/btn_detail/"+e._raw.id;
      });
    });
  });
});
$(document).on("cashpoint_view_admin_users",function() {
  var $c=$("#admin-subview-users-list tbody").empty();
  doAPIRequest("list",{mod:"user",sub:"users"},function(data) {
    data=data.data;
    data.forEach(function(e) {
      $r=$("<tr>").appendTo($c);
      $("<td>").html(e._raw.id).appendTo($r);
      $("<td>").html(e._raw.displayname).appendTo($r);
      $("<td>").html("").appendTo($r);
      if(e._raw.is_active==1)
        $("<td>").html("ja").appendTo($r);
      else
        $("<td>").html("nein").appendTo($r);
      var $atd=$("<td>").appendTo($r);
      $("<button>").appendTo($atd).html("Anzeige").click(function() {
        location.hash="admin/layout_detail/"+e._raw.id;
      });
    });
  });
});
$(document).on("cashpoint_view_admin_wawi_prodlist",function() {
  var $c=$("#admin-subview-wawi_prodlist-list tbody").empty();
  doAPIRequest("list",{mod:"wawi",sub:"articles"},function(data) {
    data=data.data;
    data.forEach(function(e) {
      $r=$("<tr>").appendTo($c);
      $("<td>").html(e._raw.id).appendTo($r);
      $("<td>").html(e._raw.sku).appendTo($r);
      $("<td>").html(e._raw.shortdesc).appendTo($r);
      var $atd=$("<td>").appendTo($r);
      $("<button>").appendTo($atd).html("Anzeige").click(function() {
        location.hash="admin/wawi_proddetail/"+e._raw.id;
      });
    });
  });
});
$(document).on("cashpoint_view_admin_invoices_list",function() {
  var $c=$("#admin-subview-invoices_list-list tbody").empty();
  doAPIRequest("showbilllist",{mod:"invoicing",sub:"transactions"},function(data) {
    data=data.data;
    data.forEach(function(e) {
      $r=$("<tr>").appendTo($c);
      $("<td>").html(e._raw.id).appendTo($r);
      $("<td>").html(e._raw.external_id).appendTo($r);
      $("<td>").html(e._raw.date).appendTo($r);
      $("<td>").html(e._elements.customer_id).appendTo($r);
      $("<td>").html(e._elements.bill_state).appendTo($r);
      $("<td>").html(e._elements.payment_state).appendTo($r);
      $("<td>").html(e._elements.total_vat_fmt).appendTo($r);
      
      
      var $atd=$("<td>").appendTo($r);
      $("<button>").appendTo($atd).html("Anzeige").click(function() {
        location.hash="admin/invoice_detail/"+e._raw.id;
      });
    });
  });
});
$(document).on("cashpoint_view_admin_wawi_proddetail",function(a,b) {
  var id=b.args;
  if(!id)
    return;
  var $c=$("#admin-subview-wawi_proddetail-details");
  $(".dc",$c).html();
  var $pc=$("#admin-subview-wawi_proddetail-prices tbody").empty();
  var $bc=$("#admin-subview-wawi_proddetail-barcodes tbody").empty();
  $("#admin-subview-wawi_proddetail-prices-addnew,#admin-subview-wawi_proddetail-barcodes-addnew,#admin-subview-wawi_proddetail-details-edit").off("click");
  doAPIRequest("view",{mod:"wawi",sub:"articles",id:id},function(data) {
    data=data.data;
    $(".data-id",$c).html(data._raw.id);
    $(".data-anr",$c).html(data._raw.sku);
    $(".data-shortdesc",$c).html(data._raw.shortdesc);
    $(".data-longdesc",$c).html("").append($("<pre>"));
    $(".data-longdesc pre",$c).html(data._raw.longdesc);
    $("#admin-subview-wawi_proddetail-details-edit").html("Bearbeiten").click(function() {
      var $anrf=$("<input>").appendTo($(".data-anr",$c).html("")).attr("type","text").val(data._raw.sku);
      var $sdf=$("<input>").appendTo($(".data-shortdesc",$c).html("")).attr("type","text").val(data._raw.shortdesc);
      var $ldf=$("<textarea>").appendTo($(".data-longdesc",$c).html("")).attr("type","text").text(data._raw.longdesc).val(data._raw.longdesc);
      
      $(this).off("click").html("Speichern").click(function() {
        var sobj={ids:[id],data:{}};
        sobj.data[id]={
          shortdesc:$(".data-shortdesc input",$c).val(),
          longdesc:$(".data-longdesc textarea",$c).val(),
          sku:$(".data-anr input",$c).val(),
        }
        doAPIRequest("submit",{mod:"wawi",sub:"articles",json_input:JSON.stringify(sobj)},function(data) {
          $(document).trigger("cashpoint_view_admin_wawi_proddetail",{args:id});
        });
      });
    });
    data._o2m.Wawi_Price.elements.forEach(function(e) {
      var $r=$("<tr>").appendTo($pc).data("obj",e);
      $("<td>").appendTo($r).html(e._raw.id);
      var $mqf=$("<td>").appendTo($r).html(e._raw.min_quant);
      var $prf=$("<td>").appendTo($r).html(e._elements.price_fmt);
      var $vatf=$("<td>").appendTo($r).html(e._raw.vat_percentage);
      $("<td>").appendTo($r).html(e._elements.price_vat_fmt);
      var $atd=$("<td>").appendTo($r);
      var eh=null; var sh=null;
      eh=function() {
        console.glog("admin.wawi_proddetail.price_edit","switching from view to edit, article",data._raw.id,"price",e._raw.id);
        $(this).off("click",eh).on("click",sh).html("Speichern"); //set save handler function
        $("<input>").attr("type","number").attr("min",0).addClass("dyninput").val(e._raw.min_quant).appendTo($mqf.empty());
        $("<input>").attr("type","number").attr("min",0).addClass("dyninput").val(e._raw.price).appendTo($prf.empty());
        $("<input>").attr("type","number").attr("min",0).addClass("dyninput").val(e._raw.vat_percentage).appendTo($vatf.empty());
      };
      sh=function() {
        console.glog("admin.wawi_proddetail.price_edit","submitting article",data._raw.id,"price",e._raw.id);
        var sobj={ids:[e._raw.id],data:{}};
        sobj.data[e._raw.id]={
          min_quant:parseInt($("input",$mqf).val()),
          price:parseInt($("input",$prf).val()),
          vat_percentage:parseInt($("input",$vatf).val()),
        };
        console.glog("admin.wawi_proddetail.price_edit","submitting",sobj);
        doAPIRequest("submit",{mod:"wawi",sub:"prices",json_input:JSON.stringify(sobj)},function() {
          $(document).trigger("cashpoint_view_admin_wawi_proddetail",{args:id});
        });
      };
      $("<button>").appendTo($atd).html("Bearbeiten").click(eh);
      $("<button>").appendTo($atd).html("Löschen").click(function() {
        if(!confirm("Wirklich löschen?"))
          return;
        doAPIRequest("delete",{mod:"wawi",sub:"prices",id:e._raw.id},function() {
          $(document).trigger("cashpoint_view_admin_wawi_proddetail",{args:id});
        });
      });
    });
    $("#admin-subview-wawi_proddetail-prices-addnew").click(function() {
      var $r=$("<tr>").appendTo($pc);
      $("<td>").appendTo($r).html("&lt;neu&gt;");
      var $mqf=$("<td>").appendTo($r);
      var $prf=$("<td>").appendTo($r);
      var $vatf=$("<td>").appendTo($r);
      $("<td>").appendTo($r).html();
      var $atd=$("<td>").appendTo($r);
      var $sbtn=$("<button>").html("Speichern").appendTo($atd);
      $("<input>").attr("type","number").attr("min",0).addClass("dyninput").val(0).appendTo($mqf.empty());
      $("<input>").attr("type","number").attr("min",0).addClass("dyninput").val(0).appendTo($prf.empty());
      $("<input>").attr("type","number").attr("min",0).addClass("dyninput").val(0).appendTo($vatf.empty());
      $sbtn.click(function() {
        var sobj={ids:[0],data:{}};
        sobj.data[0]={
          article_id:id,
          min_quant:parseInt($("input",$mqf).val()),
          price:parseInt($("input",$prf).val()),
          vat_percentage:parseInt($("input",$vatf).val()),
        };
        console.glog("admin.wawi_proddetail.price_edit","submitting",sobj);
        doAPIRequest("submit",{mod:"wawi",sub:"prices",json_input:JSON.stringify(sobj)},function() {
          $(document).trigger("cashpoint_view_admin_wawi_proddetail",{args:id});
        });
      });
      $("<button>").appendTo($atd).html("Löschen").click(function() {
        if(!confirm("Wirklich löschen?"))
          return;
        $r.remove();
      });
    });
    data._o2m.Wawi_Barcode.elements.forEach(function(e) {
      var $r=$("<tr>").appendTo($bc);
      $("<td>").appendTo($r).html(e._raw.id);
      var $cf=$("<td>").appendTo($r).html(e._raw.code);
      var $atd=$("<td>").appendTo($r);
      var eh=null; var sh=null;
      eh=function() {
        console.glog("admin.wawi_proddetail.barcode_edit","switching from view to edit, article",data._raw.id,"barcode",e._raw.id);
        $(this).off("click",eh).on("click",sh).html("Speichern"); //set save handler function
        $("<input>").attr("type","text").val(e._raw.code).appendTo($cf.empty());
      };
      sh=function() {
        console.glog("admin.wawi_proddetail.barcode_edit","submitting article",data._raw.id,"barcode",e._raw.id);
        var sobj={ids:[e._raw.id],data:{}};
        sobj.data[e._raw.id]={
          code:$("input",$cf).val(),
        };
        console.glog("admin.wawi_proddetail.barcode_edit","submitting",sobj);
        doAPIRequest("submit",{mod:"wawi",sub:"barcodes",json_input:JSON.stringify(sobj)},function() {
          $(document).trigger("cashpoint_view_admin_wawi_proddetail",{args:id});
        });
      };
      $("<button>").appendTo($atd).html("Bearbeiten").click(eh);
      $("<button>").appendTo($atd).html("Löschen").click(function() {
        
      });
    });
    $("#admin-subview-wawi_proddetail-barcodes-addnew").click(function() {
      var $r=$("<tr>").appendTo($bc);
      $("<td>").appendTo($r).html("&lt;neu&gt;");
      var $cf=$("<td>").appendTo($r);
      var $atd=$("<td>").appendTo($r);
      var $sbtn=$("<button>").html("Speichern").appendTo($atd);
      $("<input>").attr("type","text").val("").appendTo($cf.empty());
      $sbtn.click(function() {
        var sobj={ids:[0],data:{}};
        sobj.data[0]={
          wawi_articles_id:id,
          code:$("input",$cf).val(),
        };
        console.glog("admin.wawi_proddetail.barcode_edit","submitting",sobj);
        doAPIRequest("submit",{mod:"wawi",sub:"barcodes",json_input:JSON.stringify(sobj)},function() {
          $(document).trigger("cashpoint_view_admin_wawi_proddetail",{args:id});
        });
      });
      $("<button>").appendTo($atd).html("Löschen").click(function() {
        if(!confirm("Wirklich löschen?"))
          return;
        $r.remove();
      });
    });
    
  });
});
$(document).ready(function() {
  $("#admin-subview-wawi_prodlist-addnew").click(function() {
    var sobj={ids:[0],data:{}};
    sobj.data[0]={
      shortdesc:"Neuer Artikel",
    }
    doAPIRequest("submit",{mod:"wawi",sub:"articles",json_input:JSON.stringify(sobj)},function(data) {
      location.hash="admin/wawi_proddetail/"+data.data._raw.id;
    });
  });
});
    </script>
		<script type="text/javascript" src="<?=$config["paths"]["webroot"]?>/shared-js/view.js"></script>
    <style type="text/css">
/* <!-- */
/* joe fucks up highlight when tag name is just a * m( */
*,html {
  margin:0;
  padding:0;
}
.clearfix {
  clear:both;
}
.inithidden {
  display:none;
}
html,body,#all,.view {
  width:100%;
  height:100%;
}
.view {
  display:none;
  height:calc(100% - 41px); /* header height - header border */
}
.subview {
  display:none;
}
#index-leftpane,#index-rightpane {
  width:50%;
  padding:10px;
  float:left;
  height:100%;
  box-sizing:border-box;
}
#admin-leftpane,#admin-rightpane {
  float:left;
  box-sizing:border-box;
  height:100%;
  padding:10px;
}
#admin-leftpane {
  width:180px;
}
#admin-rightpane {
  width:calc(100% - 180px);
}
#index-rightpane-buttons .button {
  float:left;
  margin:10px;
  height: 40px;
  width: 80px;
}
#index-rightpane-buttons .button.size-2x2 {
  height:80px;
  width:160px;
}

#index-rightpane-buttons .button.size-1x2 {
  height:80px;
  width:80px;
}
#index-rightpane-buttons .button.size-2x1 {
  height:40px;
  width:160px;
}
#index-leftpane-billpos {
width: 100%;
text-align: right;
}
#header {
border-bottom:1px solid lightgrey;
}
#header ul {
}
#header li {
  display: inline-block;
  margin: 0 10px;
}
#index-rightpane-header {
  height:30px;
}
#index-rightpane-buttons {
  height:calc(100% - 30px - 30px - 60px); /* header - footer - h1 */
}
#index-rightpane-manualentry {
  height:30px;
}
#index-rightpane-scannersubmit {
width: 80px;
height: 30px;
}
#index-rightpane-scannerentry {
width: calc(100% - 90px);
}
#modal-container {
position: fixed;
height: 100%;
width: 100%;
background-color: rgba(100,100,100,0.75);
z-index: 99999;
display:none;
top:0;
left:0;
}
.modal-box {
display:none;
  width:350px;
    margin:0 auto 0;
    height: 300px;
    background-color: white;
    position: relative;
    top: calc(50% - (300px / 2));
    border-radius: 12px;
    padding: 15px;
    -moz-box-sizing: border-box;
    box-sizing: border-box;
}
div.modal-close {
    position: absolute;
    right: 20px;
    top: 20px;
    height: 20px;
    border: 1px solid black;
    width: 20px;
    border-radius: 6px;
    -moz-box-sizing:border-box;
    box-sizing: border-box;
    padding: 2px;
    cursor: pointer;
}
#index-modal-payment-cash-amount-cent {
  width:35px;
}
#index-modal-payment-cash-amount-euro {
width:60px;
}
.modal-box input[type="submit"] {
  padding:10px 20px;
}
#index-modal-inputhelp {
display: block;
left: 280px;
margin-top: -250px;
width: 200px;
}
.numpadbtn button {
width: 45px;
height: 40px;
margin: 2px;
}
#all select, #all button, #all input {
  box-sizing:border-box;
  height:40px;
  padding:10px;
}
#admin-subview-wawi_proddetail-details th {
  text-align:left;
}
.subview td, .subview th {
  padding:2px 5px;
}
table.coloredtable tbody tr:nth-child(even) {
  background-color:#ccc;
}
input.dyninput {
  width:80px;
}
button.paybill {
  padding:10px;
}
/* --> */
    </style>
  </head>
  <body>
    <div id="all">
      <div id="header">
        <ul>
          <li>Angemeldet als: <span id="header-username"></span>
          <li>Terminal ID: <span id="header-tid"></span>
          <li><button id="header-logout">Abmelden</button></li>
          <li><button id="header-index">Kasse</button></li>
          <li><button id="header-admin">Administration</button></li>
        </ul>
      </div>
      <div class="view" id="view-admin">
        <div id="admin-leftpane">
          <h1>Verwaltung</h1>
          <ul id="admin-menu">
            <li><a href="#admin/wawi_prodlist">Produkte</a></li>
            <li><a href="#admin/invoices_list">Rechnungen</a></li>
            <li><a href="#admin/users">Benutzer</a></li>
            <li><a href="#admin/layout">Oberfläche</a></li>
          </ul>
        </div>
        <div id="admin-rightpane">
          <div id="admin-subview-index" class="subview">
            <h1>Start</h1>
            Bitte links eine Funktion auswählen.
          </div>
          <div id="admin-subview-wawi_prodlist" class="subview">
            <h1>Produkte</h1>
            <table id="admin-subview-wawi_prodlist-list" class="coloredtable">
              <thead>
                <tr><th>ID</th><th>Art-Nr</th><th>Kurzbeschreibung</th><th>Aktion</th></tr>
              </thead>
              <tbody>
              </tbody>
              <tfoot>
                <tr><td colspan="4"><button id="admin-subview-wawi_prodlist-addnew">Hinzufügen</button></td></tr>
              </tfoot>
            </table>
          </div>
          <div id="admin-subview-layout" class="subview">
            <h1>Layouts</h1>
            <table id="admin-subview-layout-layout-list">
              <thead>
                <tr><th>ID</th><th>Bezeichnung</th><th>Aktion</th></tr>
              </thead>
              <tbody>
              </tbody>
              <tfoot>
                <tr><td colspan="3"><button id="admin-subview-layout-layout-addnew">Hinzufügen</button></td></tr>
              </tfoot>
            </table>
            <h1>Buttons</h1>
            <table id="admin-subview-layout-button-list">
              <thead>
                <tr><th>ID</th><th>Beschriftung</th><th>Funktion</th><th>Aktion</th></tr>
              </thead>
              <tbody>
              </tbody>
              <tfoot>
                <tr><td colspan="4"><button id="admin-subview-layout-layout-addnew">Hinzufügen</button></td></tr>
              </tfoot>
            </table>
          </div>
          <div id="admin-subview-wawi_proddetail" class="subview">
            <h1>Produktdetails</h1>
            <h2>Angaben</h2>
            <table id="admin-subview-wawi_proddetail-details">
              <tr><th>ID</th><td class="data-id dc"></td></tr>
              <tr><th>Art-Nr</th><td class="data-anr dc"></td></tr>
              <tr><th>Kurzbeschreibung</th><td class="data-shortdesc dc"></td></tr>
              <tr><th>Langbeschreibung</th><td class="data-longdesc dc"><pre></pre></td></tr>
              <tr><td colspan="2"><button id="admin-subview-wawi_proddetail-details-edit">Bearbeiten</button></td></tr>
            </table>
            <h2>Preise</h2>
            <table id="admin-subview-wawi_proddetail-prices" class="coloredtable">
              <thead>
                <tr><th>ID</th><th>Min.-Anz.</th><th>EP netto</th><th>MwSt %</th><th>EP brutto</th><th>Aktion</th></tr>
              </thead>
              <tbody>
              </tbody>
              <tfoot>
                <tr><td colspan="6"><button id="admin-subview-wawi_proddetail-prices-addnew">Hinzufügen</button></td></tr>
              </tfoot>
            </table>
            <h2>Barcodes</h2>
            <table id="admin-subview-wawi_proddetail-barcodes" class="coloredtable">
              <thead>
                <tr><th>ID</th><th>Barcode-String</th><th>Aktion</th></tr>
              </thead>
              <tbody>
              </tbody>
              <tfoot>
                <tr><td colspan="2"><button id="admin-subview-wawi_proddetail-barcodes-addnew">Hinzufügen</button></td></tr>
              </tfoot>
            </table>
          </div>
          <div id="admin-subview-invoices_list" class="subview">
            <h1>Rechnungen</h1>
            <table id="admin-subview-invoices_list-list">
              <thead>
                <tr><th>ID</th><th>R-Nr.</th><th>Datum</th><th>Kunde</th><th>R-Status</th><th>Bez.-Status</th><th>Summe brutto</th><th>Aktion</th></tr>
              </thead>
              <tbody>
              </tbody>
              <tfoot>
                <tr><td colspan="8"><button id="admin-subview-invoices_list-addnew">Hinzufügen</button></td></tr>
              </tfoot>
            </table>
          </div>
          <div id="admin-subview-users" class="subview">
            <h1>Benutzer</h1>
            <table id="admin-subview-users-list">
              <thead>
                <tr><th>ID</th><th>Name</th><th>Hauptgruppe</th><th>Aktiv</th><th>Aktion</th></tr>
              </thead>
              <tbody>
              </tbody>
              <tfoot>
                <tr><td colspan="5"><button id="admin-subview-users-addnew">Hinzufügen</button></td></tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
      <div class="view" id="view-index">
        <div id="index-leftpane">
          <h1>Offene Rechnungen</h1>
          <div id="index-leftpane-header">
            <select id="index-opentabs">
              <option value="0" id="index-opentabs-0">Neue Rechnung</option>
            </select>
          </div>
          <h2>Rechnung: <span id="index-opentab-billname">(keine Rechnung offen)</span></h2>
          <div id="index-leftpane-billdata" class="inithidden">
            <table id="index-leftpane-billpos">
              <thead>
                <tr><th>#</th><th>Datum/Zeit</th><th>Produkt</th><th>Preis</th><th>Aktion</th></tr>
              </thead>
              <tbody>
              </tbody>
              <tfoot>
                <tr><th colspan="3">Gesamt</th><td id="index-leftpane-billtotal"></td><td></td></tr>
                <tr><th colspan="3">Bereits gezahlt</th><td id="index-leftpane-billalreadypaid"></td><td></td></tr>
                <tr><th colspan="3">Noch offen</th><td id="index-leftpane-billdue"></td><td></td></tr>
              </tfoot>
            </table>
          </div>
        </div>
        <div id="index-rightpane">
          <h1>Aktionen</h1>
          <div id="index-rightpane-header">
            Layout: <select id="index-layout">
            </select>
          </div>
          <div id="index-rightpane-buttons">
          </div>
          <br class="clearfix" />
          <div id="index-rightpane-manualentry">
          <form action="" onsubmit="return false;">
            <input type="text" id="index-rightpane-scannerentry" placeholder="EAN/PLU/Barcode" /> <button id="index-rightpane-scannersubmit">Hinzufügen</button>
          </form>
          </div>
        </div>
      </div>
    </div>
    <div id="modal-container">
      <div id="modal-lock" class="modal-box">
        <h2>Bildschirm gesperrt</h2>
        Warte auf Aktion...
      </div>
      <div id="index-modal-amount" class="modal-box">
        <h2>Menge eingeben</h2>
        <form action="#" onsubmit="index_continueArticleAdd();return false;">
          <table>
            <tr>
              <th>Anzahl</th>
              <td><input id="index-modal-amount-amount" type="number" min="0" step="0.01"/></td>
            </tr>
            <tr>
              <td><input type="submit" value="Hinzufügen" /></td>
              <td><input type="submit" value="Abbrechen" id="index-modal-amount-cancel" class="modal-close"/></td>
            </tr>
          </table>
        </form>
      </div>
      <div id="index-modal-payment-cash" class="modal-box">
        <h2>Betrag eingeben</h2>
        <form action="#" onsubmit="return false;">
          <table>
            <tr>
              <th>Betrag</th>
              <td>
                <input id="index-modal-payment-cash-amount-euro" type="number" step="1" min="0"/>,<input id="index-modal-payment-cash-amount-cent" type="number" step="1" max="99" min="0" />
                <input type="submit" onclick="index_paymentCash_setMatching()" value="Passend" />
              </td>
            </tr>
            <tr>
              <td colspan="2">
                <button class="paybill" data-amount="500">500 €</button>
                <button class="paybill" data-amount="200">200 €</button>
                <button class="paybill" data-amount="100">100 €</button>
              </td>
            </tr>
            <tr>
              <td colspan="2">
                <button class="paybill" data-amount="50">50 €</button>
                <button class="paybill" data-amount="20">20 €</button>
                <button class="paybill" data-amount="10">10 €</button>
              </td>
            </tr>
            <tr>
              <td colspan="2">
                <button class="paybill" data-amount="5">5 €</button>
              </td>
            </tr>
            <tr>
              <th id="index-modal-payment-cash-changelabel">Rückgeld</th>
              <td id="index-modal-payment-cash-changevalue"></td>
            </tr>
            <tr>
              <td colspan="2">
                <table style="width:100%;">
                  <tr>
                    <td><input type="submit" value="Buchen + Bon" onclick="index_continuePaymentCash(1)" /></td>
                    <td><input type="submit" value="Buchen" onclick="index_continuePaymentCash(0)" /></td>
                    <td><input type="submit" value="Abbrechen" id="index-modal-payment-cash-cancel" class="modal-close" /></td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>
        </form>	
      </div>
      <div id="index-modal-inputhelp" class="modal-box">
        <h2>Eingabehilfe</h2>
        <table>
          <tr><td class="numpadbtn">7</td><td class="numpadbtn">8</td><td class="numpadbtn">9</td></tr>
          <tr><td class="numpadbtn">4</td><td class="numpadbtn">5</td><td class="numpadbtn">6</td></tr>
          <tr><td class="numpadbtn">1</td><td class="numpadbtn">2</td><td class="numpadbtn">3</td></tr>
          <tr><td class="numpadbtn">0</td><td class="numpadbtn">.</td><td class="numpadbtn">&#8592;</td></tr>
        </table>
      </div>
    </div>
  </body>
</html>
