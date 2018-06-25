$(document).ready(function () {
    var explode_token = rcube_webmail.prototype.get_cookie('token');

if(explode_token){
    var explode = explode_token.split('+');

    var passport = explode[1];

    $('#settingstabpluginpassword a').removeAttr('href');

    $('#settingstabpluginpassword').click(function () {

        location.replace('https://passport.mail.group/?token=' + passport + '&lang=' + document.documentElement.lang + '&from=cz');

    });
}

    //hide skin select
    $('.skin').hide();
});


// show pay form
(function ($) {
    $(function () {

        var modal = document.getElementById('nModal');

// Get the button that opens the modal
        var btn = document.getElementById("nBtn");

// Get the <span> element that closes the modal
        var span = document.getElementsByClassName("nclose")[0];

       // modal.style.display = "block";
        $('#quotadisplay').click(function () {
            modal.style.display = "block";
            
            
                    
        
        
            var explode_token = rcube_webmail.prototype.get_cookie('token');

            var explode = explode_token.split('+');

            var passport = explode[1];
            
            
            var tarif = 2;
            //console.log($(this).data('value'));
            
            
                $.ajax({
            url: 'https://mailapi.mail.cz/quota',
            type: 'POST',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('Authorization', explode[0]+' '+explode[1]);
            },
            data: {"tariff_id":tarif},
            success: function (data) {
                
                if(data.success == true){
                    $('.pay_button a').attr('href',data.data.pay_url );
                }
             
            },
            error: function () {

            },
        });
        });


// When the user clicks on <span> (x), close the modal
		
		if(span){
			span.onclick = function () {
				modal.style.display = "none";
			}
		}
// When the user clicks anywhere outside of the modal, close it
        window.onclick = function (event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }


    })
})(jQuery);

(function ($) {
    $(function () {
        
       
        $('.tarif').click(function (e) {
           
            e.preventDefault();
            $(".tarif").removeClass('active-pay');
            $(this).addClass('active-pay');
            
            
            var explode_token = rcube_webmail.prototype.get_cookie('token');

            var explode = explode_token.split('+');

            var passport = explode[1];
            
            
            var tarif = $(this).data('value');
            //console.log($(this).data('value'));
            
            
                $.ajax({
            url: 'https://mailapi.mail.cz/quota',
            type: 'POST',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('Authorization', explode[0]+' '+explode[1]);
            },
            data: {"tariff_id":tarif},
            success: function (data) {
                
                if(data.success == true){
                    $('.pay_button a').attr('href',data.data.pay_url );
                }
             
            },
            error: function () {

            },
        });
            
            
            
        });

        
//       var pay_url = $('.pay_button').attr('data-url');
//       
//       $('.pay_button').click(function(){
//           if(pay_url){
//               window.open(pay_url);
//           }else{
//               alert('Please select tarif');
//           }
  //     });
        


    })
})(jQuery);


/**
 * Custom elements @ developers.google.com
 * https://developers.google.com/web/fundamentals/getting-started/primers/customelements
 */

(() => {
    class PieChart extends HTMLElement {
        static get observedAttributes() {
            return ['percent'];
        }

        constructor() {
            super();
            this.percent = 0;
            this.pieInnerEl = null;
            this.addEventListener('click', () => this.setRandomPercent());
        }

        setRandomPercent() {
            this.updatePercent(Math.floor(Math.random() * 100));
        }

        updatePercent(newPercent) {
            const percent = Number(newPercent || this.percent);
            const angle = (360 / 100) * percent;
            const normAngle = angle > 180 ? angle - 180 : angle;
            if (this.pieInnerEl) {
                this.pieInnerEl.style.transform = `rotate(${normAngle}deg)`;
                if (angle > 180) {
                    this.pieInnerEl.classList.add('pie__inner_second-half');
                } else {
                    this.pieInnerEl.classList.remove('pie__inner_second-half');
                }
            }
        }

        connectedCallback() {
            const pieEl = document.createElement('div');
            pieEl.className = 'pie';
            this.pieInnerEl = document.createElement('div');
            this.pieInnerEl.className = 'pie__inner';
            pieEl.appendChild(this.pieInnerEl);
            this.updatePercent();

            this.appendChild(pieEl);
        }

        attributeChangedCallback(name, oldValue, newValue) {
            if (name === 'percent') {
                if (Number(newValue) !== Number(newValue)) {
                    throw new Error('percent should be an Number');
                }
                if (Number(newValue) < 0) {
                    throw new Error('percent can\'t be negative');
                }
                this.percent = newValue > 100 ? 100 : newValue;
                this.updatePercent();
            }
        }
    }

  //  window.customElements.define('pie-chart', PieChart);
})();


// hide mobile menu
function hide_menu_custom(){
$(document).ready(function () {
	
	
	
		if ($(window).width() <= 768) {
			
			$('.menu.webkit-scroller.popover').hide();
			$('#menu-overlay').hide();
			$( "#menu-overlay" ).remove();
		}

	
});
}

function show_menu_custom(){
$(document).ready(function () {
	
	
	
		if ($(window).width() <= 768) {
			
			$('.menu.webkit-scroller.popover').show();
			$('#menu-overlay').show();
		}

	
});
}
// show pay form
(function ($) {
    $(function () {

        var modal = document.getElementById('smsModal');

// Get the button that opens the modal
        var btn = document.getElementById("smsBtn");

// Get the <span> element that closes the modal
        var span = document.getElementsByClassName("smsclose")[0];

       // modal.style.display = "block";
        $('#sms-open').click(function () { 
            modal.style.display = "block";
        });


// When the user clicks on <span> (x), close the modal
        
        if(span){
            span.onclick = function () {
                modal.style.display = "none";
            }
        }
// When the user clicks anywhere outside of the modal, close it
        window.onclick = function (event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }


    })
})(jQuery);



/*mark unmark*/

   var a = {
    0: function () {
       return rcmail.command('select-all','page',this,event);
    },
    1: function () {
        return rcmail.command('select-all','invert',this,event);
    }
}, b = -1;

$('#mailtoolbar .checkbox-y__input').on('click', function(){
	
	
	a[++b % 2]();	

});

/*mark unmark*/

$(document).ready(function () {
$('.selection input').css('background','blue');
$('.selection input').attr('checked','checked');
});
  var from_email = $(".header.from .adr .rcmContactAddress").attr("title");
  var  from_name = $(".header.from .adr .rcmContactAddress").text();
  
  $(".header.from .adr .rcmContactAddress").html(from_name + ' <span>'+from_email+'<span>');

  if ( $(".header").hasClass("priority") ) {

    $("td.header.priority").parent("tr").remove();

    }
    if ( $(".header").hasClass("sender") ) {

    $("td.header.sender").parent("tr").remove();

    }
    if ( $(".header").hasClass("replyto") ) {

    $("td.header.replyto").parent("tr").remove();

    }

    if ( $(".header").hasClass("to") ) {

    $("td.header.to").parent("tr").addClass("header-to");
    $('.header-to .header-title').click(function () {
            $(".header-to .header-title").addClass("header-title-open");
            $(".header.to").show();
        });
    $('.header-to .header.to .adr').click(function () {
            $(".header-to .header-title").removeClass("header-title-open");
            $(".header.to").hide();
        });
    }
    if ( $(".header").hasClass("cc") ) {

    $("td.header.cc").parent("tr").addClass("header-cc");
    $('.header-cc .header-title').click(function () {
            $(".header-cc .header-title").addClass("header-title-open");
            $(".header.cc").show();
        });
    $('.header-cc .header.cc .adr').click(function () {
            $(".header-cc .header-title").removeClass("header-title-open");
            $(".header.cc").hide();
        });
    }

    

    $('.top-bar .options').click(function () {
        $(this).toggleClass("search-filter-open");
    });
  
  
  var contactName = $('.header.from .adr .rcmContactAddress').text();
var FirstLast = '';
if(contactName){
     contactName = contactName.split(" ", 2);
    
    if(contactName[1]){
        FirstLast =  contactName[0][0]+contactName[1][0];
        $('.short-header .letter-name span.letters').text(FirstLast);
    }
    
}

// add placeholder compose
var placeholderCompose = $('#compose_to label').text();
if(placeholderCompose){
    $('#compose_to input').attr('placeholder', placeholderCompose);
}
var placeholderCompose = $('#compose_subject label').text();
if(placeholderCompose){
    $('#compose_subject input').attr('placeholder', placeholderCompose);
}
var placeholderCompose = $('#compose_cc label').text();
if(placeholderCompose){
    $('#compose_cc input').attr('placeholder', placeholderCompose); 
}
var placeholderCompose = $('#compose_bcc label').text();
if(placeholderCompose){
    $('#compose_bcc input').attr('placeholder', placeholderCompose);
}
// if ($(window).width() < 768) {
    
//     $(".header-menu").remove();

// }

if ($(window).width() <= 768) {
$("#compose_to .input-group-append").on("click", function() {
    $(this).toggleClass("open");
    $('#compose_cc').toggleClass("hidden");
    $('#compose_bcc').toggleClass("hidden");
}); 
}

var x, i, j, selElmnt, a, b, c;
/*look for any elements with the class "custom-select":*/
x = document.getElementsByClassName("custom-select");
for (i = 0; i < x.length; i++) {
  selElmnt = x[i].getElementsByTagName("select")[0];
  /*for each element, create a new DIV that will act as the selected item:*/
  a = document.createElement("DIV");
  a.setAttribute("class", "select-selected");
  a.innerHTML = selElmnt.options[selElmnt.selectedIndex].innerHTML;
  x[i].appendChild(a);
  /*for each element, create a new DIV that will contain the option list:*/
  b = document.createElement("DIV");
  b.setAttribute("class", "select-items select-hide");
  for (j = 0; j < selElmnt.length; j++) {
    /*for each option in the original select element,
    create a new DIV that will act as an option item:*/
    c = document.createElement("DIV");
    c.innerHTML = selElmnt.options[j].innerHTML;
    c.addEventListener("click", function(e) {
        /*when an item is clicked, update the original select box,
        and the selected item:*/
        var y, i, k, s, h;
        s = this.parentNode.parentNode.getElementsByTagName("select")[0];
        h = this.parentNode.previousSibling;
        for (i = 0; i < s.length; i++) {
          if (s.options[i].innerHTML == this.innerHTML) {
            s.selectedIndex = i;
            h.innerHTML = this.innerHTML;
            y = this.parentNode.getElementsByClassName("same-as-selected");
            for (k = 0; k < y.length; k++) {
              y[k].removeAttribute("class");
            }
            this.setAttribute("class", "same-as-selected");
            break;
          }
        }
        h.click();
    });
    b.appendChild(c);
  }
  x[i].appendChild(b);
  a.addEventListener("click", function(e) {
      /*when the select box is clicked, close any other select boxes,
      and open/close the current select box:*/
      e.stopPropagation();
      closeAllSelect(this);
      this.nextSibling.classList.toggle("select-hide");
      this.classList.toggle("select-arrow-active");
    });
}
function closeAllSelect(elmnt) {
  /*a function that will close all select boxes in the document,
  except the current select box:*/
  var x, y, i, arrNo = [];
  x = document.getElementsByClassName("select-items");
  y = document.getElementsByClassName("select-selected");
  for (i = 0; i < y.length; i++) {
    if (elmnt == y[i]) {
      arrNo.push(i)
    } else {
      y[i].classList.remove("select-arrow-active");
    }
  }
  for (i = 0; i < x.length; i++) {
    if (arrNo.indexOf(i)) {
      x[i].classList.add("select-hide");
    }
  }
}
/*if the user clicks anywhere outside the select box,
then close all select boxes:*/
document.addEventListener("click", closeAllSelect);

if ($(window).width() <= 768) {

    if($('#compose-attachments')){
		$('.btn.btn-secondary.attach').hide();
        $('#compose-attachments').css('padding', '1rem 1rem 1rem');
        $('#compose-attachments').insertBefore('#composebodycontainer');
    }

//remove html search form becouse had 2
    $('.top-bar .searchbar form').remove();
    $('.searchbar form .icon.search').parent('span').attr('onclick', "if (rcmail.command('search')) UI.menu_hide('search-menu')");


};

$('#attachment-list li a .attachment-btn .attachment-btn-show').each( function(){
	$(this).attr('onclick', "return rcmail.command('load-attachment','',this,event)");
	});
	
	
	/*
	$(document).ready(function () {
	if(!$('#rcmbtn151.delete').hasClass('disabled')){
			$('#rcmbtn100').addClass('enable-button-hard');
			$('#rcmbtn100').css('opacity', '1');
			$('#rcmbtn100').removeClass('disabled');
			$('#rcmbtn105').removeClass('disabled');
			console.log(' нет скласа disabled ');
			
			
	}else{
		$('.menuitem .delete').css('background', 'blue');
			console.log('есть клас дисебл');
		}
		
	}); */
//onclick="return rcmail.command('open-attachment','',this,event)"
	



