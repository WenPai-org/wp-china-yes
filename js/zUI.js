(function($){

	$.zUI = $.zUI || {}
	$.zUI.emptyFn = function(){};
	$.zUI.asWidget = [];
	/*
	 * core代码，定义增加一个插件的骨架
	 */
	$.zUI.addWidget = function(sName,oSefDef){
		//设置规范中的常量sFlagName、sEventName、sOptsName
		$.zUI.asWidget.push(sName);
		var w = $.zUI[sName] = $.zUI[sName] || {};
		var sPrefix = "zUI" + sName
		w.sFlagName = sPrefix;
		w.sEventName = sPrefix + "Event";
		w.sOptsName = sPrefix + "Opts";
		w.__creator = $.zUI.emptyFn;
		w.__destroyer = $.zUI.emptyFn;
		$.extend(w,oSefDef);
		w.fn = function(ele,opts){
			var jqEle = $(ele);
			jqEle.data(w.sOptsName,$.extend({},w.defaults,opts));
			//如果该元素已经执行过了该插件，直接返回，仅相当于修改了配置参数
			if(jqEle.data(w.sFlagName)){
				return;
			}
			jqEle.data(w.sFlagName,true);
			w.__creator(ele);
			jqEle.on(jqEle.data(w.sEventName));
		};
		w.unfn = function(ele){
			w.__destroyer(ele);
			var jqEle = $(ele);//移除监听事件
			if(jqEle.data(w.sFlagName)){
				jqEle.off(jqEle.data(w.sEventName));
				jqEle.data(w.sFlagName,false);
			}
		}
		
	}
	/*
	 * draggable
	 * 参数：obj{
	 * bOffsetParentBoundary:是否以定位父亲元素为边界,
	 * oBoundary:指定元素left和top的边界值，形如{iMinLeft:...,iMaxLeft:...,iMinTop:...,iMaxTop:...},与上一个参数互斥
	 * fnComputePosition:扩展函数，返回形如{left:...,top:...}的对象
	 * }
	 * 支持的自定义事件:
	 * "draggable.start":drag起始，就是鼠标down后触发
	 * "draggable.move":drag过程中多次触发
	 * "draggable.stop":drag结束触发，就是鼠标up后触发
	 */
	//注册draggable组件
	$.zUI.addWidget("draggable",{
		defaults:{
			bOffsetParentBoundary:false,//是否以定位父亲元素为边界
			oBoundary:null,//边界
			fnComputePosition:null//计算位置的函数
		},
		__creator:function(ele){
			var jqEle = $(ele);
			jqEle.data($.zUI.draggable.sEventName,{
			mousedown:function(ev){
			var jqThis = $(this);
			var opts = jqThis.data($.zUI.draggable.sOptsName);
			
			jqThis.trigger("draggable.start");
			var iOffsetX = ev.pageX - this.offsetLeft;
			var iOffsetY = ev.pageY - this.offsetTop;
			
			function fnMouseMove (ev) {
				var oPos = {};
				if(opts.fnComputePosition){
					oPos = opts.fnComputePosition(ev,iOffsetX,iOffsetY);
				}else{
					oPos.iLeft = ev.pageX - iOffsetX;
					oPos.iTop = ev.pageY - iOffsetY;
				}
				
				var oBoundary = opts.oBoundary;
				if(opts.bOffsetParentBoundary){//如果以offsetParent作为边界
					var eParent = jqThis.offsetParent()[0];
					oBoundary = {};
					oBoundary.iMinLeft = 0;
					oBoundary.iMinTop = 0;
					oBoundary.iMaxLeft = eParent.clientWidth - jqThis.outerWidth();
					oBoundary.iMaxTop = eParent.clientHeight - jqThis.outerHeight();
				}
			
				if(oBoundary){//如果存在oBoundary，将oBoundary作为边界
					oPos.iLeft = oPos.iLeft < oBoundary.iMinLeft ? oBoundary.iMinLeft : oPos.iLeft;
					oPos.iLeft = oPos.iLeft > oBoundary.iMaxLeft ? oBoundary.iMaxLeft : oPos.iLeft;
					oPos.iTop = oPos.iTop < oBoundary.iMinTop ? oBoundary.iMinTop : oPos.iTop;
					oPos.iTop = oPos.iTop > oBoundary.iMaxTop ? oBoundary.iMaxTop : oPos.iTop;
				}
				
				jqThis.css({left:oPos.iLeft,top:oPos.iTop});
				ev.preventDefault();
				jqThis.trigger("draggable.move");
			}
			
			var oEvent = {
				mousemove:fnMouseMove,
				mouseup:function(){
					$(document).off(oEvent);
					jqThis.trigger("draggable.stop");
				}
			};
			$(document).on(oEvent);
		}});
		}
	});
	/*
	 * panel
	 * 参数：obj{
	 * 	iWheelStep:鼠标滑轮滚动时步进长度
	 *	sBoxClassName:滚动框的样式
	 * 	sBarClassName:滚动条的样式
	 * }
	 */
	$.zUI.addWidget("panel",{
		defaults : {
				iWheelStep:16,
				sBoxClassName:"zUIpanelScrollBox",
				sBarClassName:"zUIpanelScrollBar"
		},
		__creator:function(ele){
			var jqThis = $(ele);
			//如果是static定位，加上relative定位
			if(jqThis.css("position") === "static"){
				jqThis.css("position","relative");
			}
			jqThis.css("overflow","hidden");
			
			//必须有一个唯一的直接子元素,给直接子元素加上绝对定位
			var jqChild = jqThis.children(":first");
			if(jqChild.length){
				jqChild.css({top:0,position:"absolute"});
			}else{
				return;
			}
			
			var opts = jqThis.data($.zUI.panel.sOptsName);
			//创建滚动框
			var jqScrollBox = $("<div style='position:absolute;display:block;line-height:0;'></div>");
			jqScrollBox.addClass(opts.sBoxClassName);
			//创建滚动条
			var jqScrollBar= $("<div style='position:absolute;display:block;line-height:0;'></div>");
			jqScrollBar.addClass(opts.sBarClassName);
			jqScrollBox.appendTo(jqThis);
			jqScrollBar.appendTo(jqThis);
			
			opts.iTop = parseInt(jqScrollBox.css("top"));
			opts.iWidth = jqScrollBar.width();
			opts.iRight = parseInt(jqScrollBox.css("right"));
			
			//添加拖拽触发自定义函数
			jqScrollBar.on("draggable.move",function(){
				var opts = jqThis.data($.zUI.panel.sOptsName);
				fnScrollContent(jqScrollBox,jqScrollBar,jqThis,jqChild,opts.iTop,0);
			});
			
		  //事件对象
			var oEvent ={
				mouseenter:function(){
					fnFreshScroll();
					jqScrollBox.css("display","block");
					jqScrollBar.css("display","block");
				},
				// mouseleave:function(){
				// 	jqScrollBox.css("display","none");
				// 	jqScrollBar.css("display","none");
				// }
			};
			
			oEvent.mouseenter();
			var sMouseWheel = "mousewheel";
			if(!("onmousewheel" in document)){
				sMouseWheel = "DOMMouseScroll";
			}
			oEvent[sMouseWheel] = function(ev){
				var opts = jqThis.data($.zUI.panel.sOptsName);
				var iWheelDelta = 1;
				ev.preventDefault();//阻止默认事件
				ev = ev.originalEvent;//获取原生的event
				if(ev.wheelDelta){
						iWheelDelta = ev.wheelDelta/120;
				}else{
						iWheelDelta = -ev.detail/3;
				}
				var iMinTop = jqThis.innerHeight() - jqChild.outerHeight();
				//外面比里面高，不需要响应滚动
				if(iMinTop>0){
					jqChild.css("top",0);
					return;
				}
				var iTop = parseInt(jqChild.css("top"));
				var iTop = iTop + opts.iWheelStep*iWheelDelta;
				iTop = iTop > 0 ? 0 : iTop;
				iTop = iTop < iMinTop ? iMinTop : iTop;
				jqChild.css("top",iTop);
				fnScrollContent(jqThis,jqChild,jqScrollBox,jqScrollBar,0,opts.iTop);
			}
			//记录添加事件
			jqThis.data($.zUI.panel.sEventName,oEvent);
			//跟随滚动函数
			function fnScrollContent(jqWrapper,jqContent,jqFollowWrapper,jqFlollowContent,iOffset1,iOffset2){
				var opts = jqThis.data($.zUI.panel.sOptsName);
				var rate = (parseInt(jqContent.css("top"))-iOffset1)/(jqContent.outerHeight()-jqWrapper.innerHeight())//卷起的比率
				var iTop = (jqFlollowContent.outerHeight()-jqFollowWrapper.innerHeight())*rate + iOffset2;
				jqFlollowContent.css("top",iTop);
			}
		
			//刷新滚动条
			function fnFreshScroll(){
	
				var opts = jqThis.data($.zUI.panel.sOptsName);
				var iScrollBoxHeight = jqThis.innerHeight()-2*opts.iTop;
				var iRate = jqThis.innerHeight()/jqChild.outerHeight();
				var iScrollBarHeight = iScrollBarHeight = Math.round(iRate*iScrollBoxHeight);
				//如果比率大于等于1，不需要滚动条,自然也不需要添加拖拽事件
				if(iRate >= 1){
					jqScrollBox.css("height",0);
					jqScrollBar.css("height",0);
					return;
				}
				jqScrollBox.css("height",iScrollBoxHeight);
				jqScrollBar.css("height",iScrollBarHeight);
				//计算拖拽边界，添加拖拽事件
				var oBoundary = {iMinTop:opts.iTop};
				oBoundary.iMaxTop = iScrollBoxHeight - Math.round(iRate*iScrollBoxHeight)+opts.iTop;
				oBoundary.iMinLeft = jqThis.innerWidth() - opts.iWidth - opts.iRight;
				oBoundary.iMaxLeft = oBoundary.iMinLeft;
				fnScrollContent(jqThis,jqChild,jqScrollBox,jqScrollBar,0,opts.iTop);
				jqScrollBar.draggable({oBoundary:oBoundary});
			}
		},
			__destroyer:function(ele){
				var jqEle = $(ele);
				if(jqEle.data($.zUI.panel.sFlagName)){
					var opts = jqEle.data($.zUI.panel.sOptsName);
					jqEle.children("."+opts.sBoxClassName).remove();
					jqEle.children("."+opts.sBarClassName).remove();
				}
		}
	});
	
	$.each($.zUI.asWidget,function(i,widget){
		unWidget = "un"+widget;
		var w = {};
		w[widget] = function(args){
				this.each(function(){
				$.zUI[widget].fn(this,args);
			});
			return this;
		};
		w[unWidget] = function(){
				this.each(function(){
				$.zUI[widget].unfn(this);
			});
			return this;
		}
		$.fn.extend(w);
	});
	})(jQuery);
		