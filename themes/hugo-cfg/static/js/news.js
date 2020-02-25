// try to read schedule from json

$(document).ready(function(e) {
  // load only on page with newsletter selector
  if ($("#newsletter").length) {
      //$.getJSON("/news/news.json", function(result){
      $.ajaxSetup({ cache: false });
      var langUrl = "https://raw.githubusercontent.com/CodeforKarlsruhe/codeforka/master/static/news/news.json";
      //var langUrl = "/news/news.json";
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
            sc += "<h2>" + news.title + "</h2>"
            sc += news.date + "<br>"

            $.each(news.items, function(i, field){
                console.log("News: ",i)
                sc += "<div class=\" preview " + ((i%2) ? "news-odd" : "news-even") + "\">"
                sc += "<h3>" + field.headline + "</h3>"
                sc += "<img class=\"news-img\" src=\"" + field.imglink + "\" + title=\"" + field.imgtitle + "\">"
                sc += "<p>" + field.text + "</p>";
                //sc += "<p>" + field.teaser + "</p>";
                sc += "<a href=\"" +  field.more + "\">Mehr</a>";
                sc += "</div>"
            });
            sc += "</div>";
            // append
            //$("#newsletter").append(sc);
            //$("#newsletter").html("</h1>" + sc);
            $("#newsletter").parent().html(sc);
        }
      });
  } else
    console.log("No newsletter id")
});

// language toggle
function langToggle() {
    console.log("toggle")
}
