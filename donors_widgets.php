<?php
/**
 * 仪表盘赞助商列表小部件
 *
 * @author    Jialong
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPLv3 Licence
 */
$plugin_url = plugin_dir_url(__FILE__);
echo <<<ETO
	<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document</title>
  <link rel="stylesheet" type="text/css" href="{$plugin_url}css/global.css"/>

</head>
<body>
  <div>
    <div id="sponsor-container"></div>
      <div class="url-container">
        <a class="url-item" target="_blank" href="https://www.ibadboy.net/archives/3204.html">项目主页</a>
        <a href="#" class="url-item addGroup">入群交流</a>
        <a class="url-item" target="_blank" href="https://www.ibadboy.net/archives/3204.html#%E5%B8%B8%E8%A7%81%E9%97%AE%E9%A2%98">常见问题</a>
        <div class="close">不再显示</div>
    </div>
  </div>
</body>
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
  var data = mode.split(',')
  var _html = '';
  for (let i = 0; i < data.length; i++) {
    _html += '<div class="sponsor-item-content">' + data[i] + '</div>';
  }
  return _html;
}

jQuery('.addGroup').on('click', function () {
  alert('加入WP中国仓库源建设计划QQ交流群：1046115671')
})

jQuery('.close').on('click', function () {
  window.open("https://www.ibadboy.net/archives/3683.html");
})

</script>
</html>

ETO;
