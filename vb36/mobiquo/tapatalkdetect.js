function detectTapatalk() {
    if (document.cookie.indexOf("tapatalk_redirect4=false") < 0) {
        if (!navigator.userAgent.match(/Opera/i)) {

            if (navigator.userAgent.match(/iPhone|iPod/i)) {
                setTapatalkCookies();
                if (confirm("This forum has an app for iPhone and iPod Touch! Click OK to learn more about Tapatalk."))
                    window.location = "http://itunes.apple.com/us/app/tapatalk-forum-app/id307880732?mt=8";
            } else if(navigator.userAgent.match(/iPad/i)) {
                setTapatalkCookies();
                if (confirm("This forum has an app for iPad! Click OK to learn more about Tapatalk."))
                    window.location = "http://itunes.apple.com/us/app/tapatalk-forum-app/id307880732?mt=8";
            } else if(navigator.userAgent.match(/Kindle Fire/i)) {
                setTapatalkCookies();
                if (confirm("This forum has an app for Kindle Fire! Click OK to learn more about Tapatalk."))
                    window.location = "http://www.amazon.com/gp/mas/dl/android?p=com.quoord.tapatalkpro.activity";
            } else if(navigator.userAgent.match(/Android/i)) {
                setTapatalkCookies();
                if (confirm("This forum has an app for Android. Click OK to learn more about Tapatalk."))
                    window.location = "market://details?id=com.quoord.tapatalkpro.activity";
            }
        }
    }
}

function setTapatalkCookies() {
    var date = new Date();
    var days = 90;
    date.setTime(date.getTime()+(days*24*60*60*1000));
    var expires = "; expires="+ date.toGMTString();
    var domain = "; path=/";
    document.cookie = "tapatalk_redirect4=false" + expires + domain; 
}

detectTapatalk();