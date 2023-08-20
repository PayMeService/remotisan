@if(!($ngApp ?? null))
    var RemotisanApp = angular.module('RemotisanApp', []);
@endif

{{ $ngApp ?? "RemotisanApp" }}.controller('RemotisanController', ["$scope", "$http", "$timeout", "$sce", "$location", function($scope, $http, $timeout, $sce, $location) {
$scope.baseUrl = '';
$scope.commands = [];
$scope.historyRecords = [];
$scope.command = null;
$scope.params = '';
$scope.$location = {};
$scope.showHistory = false;
$scope.showExecButton = true;
$scope.showHelp = false;
$scope.log = {
uuid: null,
content: "",
};
$scope.proc_statuses = {
"1": "RUNNING",
"2": "COMPLETED",
"3": "FAILED",
"4": "KILLED"
};

$scope.$watch('showHistory', function(newVal,oldVal){
if(newVal) {
$scope.getHistory();
}
}, true);

$scope.init = function(baseUrl) {
$scope.baseUrl = baseUrl;
$scope.fetchCommands();
if($location.path() != '') {
$scope.log.uuid = $location.path().replace('/', '');
$scope.readLog();
}
};

$scope.statusCodeToHumanReadable = function (status_code) {
return $scope.proc_statuses[status_code];
}

$scope.showKilledByIfStatusKilled = function(recordData) {
if(recordData.process_status === 4) { // proc_statuses 4 = killed.
return "(" + recordData.killed_by + ")";
}
return "";
}

$scope.locationPath = function (newPath)
{
return $location.path(newPath);
}

$scope.onChangeDropdownValue = function () {
$scope.params = '';
}

$scope.reRun = function(command, parameters) {
if (!confirm("Are you sure to re-run \"" + command + " " + parameters + "\" command ?")) { return; }
$scope.command = command;
$scope.params = parameters;
$scope.execute();
}

$scope.lockExecButton = function() {
$scope.showExecButton = false;
}

$scope.unlockExecButton = function() {
$scope.showExecButton = true;
}

$scope.execute = function () {
$scope.resetLog();
$scope.lockExecButton();
// show loader
$http.post($scope.baseUrl + "/execute", {
command: $scope.command,
params: $scope.params
}).then(function (response) {
$scope.log.uuid = response.data.id;
$timeout( function(){
$scope.readLog();
},
5000
);
$scope.refreshHistoryIfNeeded();
}, function (response) {
$scope.unlockExecButton();
$scope.refreshHistoryIfNeeded();
});
};

$scope.refreshHistoryIfNeeded = function() {
if($scope.showHistory) { $scope.getHistory(); }
}

$scope.getHistory = function(){
$http.get($scope.baseUrl + "/history").then(function(response){
$scope.historyRecords = response.data;
}, function(response){
console.log(response);
});
};

$scope.killProcess = function(uuid){
if (!confirm("Do you really want to kill the job " + uuid + " ?")) { return; }
$http.post($scope.baseUrl + "/kill/" + uuid)
.then(function(response){
console.log("Response success", response.data);
$scope.refreshHistoryIfNeeded();
alert("Process killed");
},function(response){
console.log(response);
var alertInfo = "";
if(response.status == 409) {
alertInfo = "Kill already in progress";
}else if(response.status == 422) {
alertInfo = "Process already killed";
}else if(response.status == 401) {
alertInfo = "Not allowed!";
}else{
alertInfo = "Server Error";
}
alert(alertInfo);
});
};

$scope.fetchCommands = function () {
$http.get($scope.baseUrl + "/commands")
.then(function (response) {
$scope.commands = response.data.commands;
}, function (response) {
console.log(response);
});
};

$scope.resetLog = function() {
$scope.log = {
uuid: null,
content: "",
};
}

$scope.copyToClipboard = function(text) {
navigator.clipboard.writeText(text);
}

$scope.readLog = function (log_uuid = null) {
$scope.log.uuid = log_uuid || $scope.log.uuid;
$http.get($scope.baseUrl + "/execute/" + $scope.log.uuid)
.then(function (response) {
$scope.locationPath($scope.log.uuid);
console.log(response.data);
$scope.log.content = response.data.content.join("\n");
if (!response.data.isEnded) {
$timeout( function(){ $scope.readLog(); }, 1000);
}
$scope.unlockExecButton();
$scope.refreshHistoryIfNeeded();
}, function (response) {
console.log(response);
$scope.unlockExecButton();
$scope.refreshHistoryIfNeeded();
});
};
}]);
