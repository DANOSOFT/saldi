$(document).ready(function(){
  $(".menu-btn").on('click',function(e){
    e.preventDefault();
    
    //Check this block is open or not..
    if(!$(this).prev().hasClass("open")) {
      $(".header").slideDown(400);
      $(".header").addClass("open");
      $(this).find("i").removeClass().addClass("fa fa-chevron-up");
    }
    
    else if($(this).prev().hasClass("open")) {
      $(".header").removeClass("open");
      $(".header").slideUp(400);
      $(this).find("i").removeClass().addClass("fa fa-chevron-down");
    }
  });
});