<?php
/**
 * 仪表盘赞助商列表小部件
 *
 * @author    Jialong
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPLv3 Licence
 */
$plugin_url = plugin_dir_url(__FILE__);
echo <<<ETO
<style>
.sponsor-item-logo{width:70px;height:60px;}.sponsor-item-container{height:68px;display:flex;min-width:350px;line-height:24px;margin-bottom:10px;border-bottom:1px solid #eee;}.sponsor-item-synopsis{margin-left:10px;}.sponsor-item-title{color:#000;cursor:pointer;text-decoration:none;}.sponsor-item-title:hover{opacity:0.5;color: #181717;}.sponsor-item-title-icon{width:16px;margin-right:8px;}.sponsor-item-content{height:24px;padding:0 10px;line-height:20px;font-size:12px;margin:3px 8px 0 0;color:#409eff;border-radius:4px;white-space:nowrap;display:inline-block;box-sizing:border-box;background-color:#ecf5ff;border:1px solid #d9ecff;}#sponsor-container{height:300px;overflow:auto;overflow-x:hidden;}#sponsor-container::-webkit-scrollbar{width:5px;}#sponsor-container::-webkit-scrollbar-thumb{border-radius:10px;background:#d0d1d2;box-shadow:inset 0 0 5px #fff;}#sponsor-container::-webkit-scrollbar-track{background:#fff;border-radius:10px;box-shadow:inset 0 0 5px #fff;}.url-container{padding:10px 0 0;}.url-item{margin:10px;text-decoration:none;}.close{float:right;cursor:pointer;}.sponsor-tag-container{white-space:nowrap;}
</style>

<div id="sponsor-container"></div>
<div class="url-container">
  <a class="url-item" target="_blank" href="https://www.ibadboy.net/archives/3204.html">项目主页</a>
  <a href="#" class="url-item addGroup">入群交流</a>
  <a class="url-item" target="_blank" href="https://www.ibadboy.net/archives/3204.html#%E5%B8%B8%E8%A7%81%E9%97%AE%E9%A2%98">常见问题</a>
  <div class="close">不再显示</div>
</div>
  
<script type="text/javascript">
jQuery.ajax({
  url: "https://wp-mirror-dev.ibadboy.net/api/v1/donors",
  type: "GET",  
  dataType: "json",
  success: function (data) {
    for (let i = 0; i < data.data.length; i++) {
      var _html = '<div class="sponsor-item-container">' +
      '<img class="sponsor-item-logo" src="' + data.data[i].logo_url + '" alt="logo" />' +
      '<div class="sponsor-item-synopsis">' +
      '<a href="'+data.data[i].url+'"  target="_blank" class="sponsor-item-title">' +
      '<img class="sponsor-item-title-icon" src="' + getIcon(data.data[i].type) + '" />' +
      data.data[i].name + '</a>' +
      '<div class="sponsor-tag-container">' + getSubsidize(data.data[i].mode) + '</div>' + '</div>' +
      '</div>'; 
        jQuery("#sponsor-container").append(_html)
    }
  }
});

function getIcon(type) {
  switch (type) {
    case 1:
    return '{$plugin_url}image/enterprise.svg';
    case 2:
      return '{$plugin_url}image/personage.svg';
    case 3:
      return '{$plugin_url}image/school.svg';
    default:
      break;
  }
}

function getSubsidize(mode) {
  const data = mode.split(',');
  let _html = '';
  for (let i = 0; i < data.length; i++) {
    _html += '<div class="sponsor-item-content">' + data[i] + '</div>';
  }
  return _html;
}

jQuery('.addGroup').on('click', function () {
  alert('QQ群：1046115671');
});

jQuery('.close').on('click', function () {
  window.open("https://www.ibadboy.net/archives/3683.html");
});
</script>
ETO;
