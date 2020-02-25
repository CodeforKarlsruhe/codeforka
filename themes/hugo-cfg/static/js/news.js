// try to read schedule from json

$(document).ready(function(e) {
  // load only on page with newsletter selector
  if ($("#newsletter").length) {
      //$.getJSON("/news/news.json", function(result){
      $.ajaxSetup({ cache: false });
      //var langUrl = "https://raw.githubusercontent.com/CodeforKarlsruhe/labNews/master/news.json";
      var langUrl = "/news/news.json";
          $.getJSON(langUrl, function(result){
        if (0 == result.length)
            console.log("No newsletter data")
        else {
            console.log("Length " + result.length)
            if (lang && lang == "en") {
                  var news = result.en
            } else {
                  var news = result.de
            }

            var sc = "<div class=\"newsletter projects\">";
            sc += "<h1>" + news.title + "</h1>"
            sc += news.date + "<br>"

            $.each(news.items, function(i, field){
                console.log("News: ",i)
                sc += "<div class=\"" + ((i%2) ? "news-odd" : "news-even") + " preview\">"
                sc += "<h2>" + field.headline + "</h2>"
                sc += "<img class=\"news-img\" src=\"" + field.imglink + "\" + title=\"" + field.imgtitle + "\">"
                sc += "<p>" + field.text + "</p>";
                sc += "</div>"
            });
            sc += "</div>";
            // append
            //$("#newsletter").append(sc);
            $("#newsletter").html(sc);
        }
      });
  } else
    console.log("No newsletter id")
});

// language toggle
function langToggle() {
    console.log("toggle")
}
