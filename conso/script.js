
var lastValue =  document.getElementById('lastValue');
lastValue.style.visibility = "hidden";


var nextValue  =  document.getElementById('nextValue');
nextValue.style.visibility = "hidden";
nextValue.onclick = function(){
    lastValue.style.visibility = "";
}


var allUsers  =  document.getElementById('allUsers');
allUsers.onclick = function(){
    nextValue.style.visibility = "";
}


var searchByNumber  =  document.getElementById('searchByNumber');
searchByNumber.onclick = function(){
    nextValue.style.visibility = "hidden";
    lastValue.style.visibility = "hidden";
}

