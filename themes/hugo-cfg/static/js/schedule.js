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

                // check location, and online, if it is there
                if (typeof(undefined) !== typeof(field.location)) {
                    if (0 == field.location.search("http"))
                        sc += ", &nbsp;<a href=\"" + field.location + "\" target=\"_blank\">Online</a>"
                    else
                        sc += ",&nbsp;" + field.location
                }

                if (typeof(undefined) !== typeof(field.ics))
                    sc += "&nbsp;("+ field.ics + ")"
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

