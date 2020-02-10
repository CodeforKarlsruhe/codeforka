// try to read schedule from json

$(document).ready(function(e) {
  // load only on page with schedule selector
  if ($("#schedule").length) {
      //$.getJSON("/schedule/schedule.json", function(result){
      $.ajaxSetup({ cache: false });
      var langUrl = "https://raw.githubusercontent.com/CodeforKarlsruhe/labSchedule/master/karlsruhe.json";
          $.getJSON(langUrl, function(result){
        if (0 == result.length) 
            console.log("No schedule data")
        else {
            console.log("Length " + result.length)
            if (lang && lang == "en") {
                  var schedule = result.en
            } else {
                  var schedule = result.de
            }

            var sc = "<ul>";
            $.each(schedule, function(i, field){
                sc += "<li>";
                sc += field.date + ": " + field.title;
                sc += "</li>";
            });
            sc += "</ul>";
            $("#schedule").append(sc);
        }
      });
  } else
    console.log("No schedule id")
});

// language toggle
function langToggle() {
    console.log("toggle")
}

