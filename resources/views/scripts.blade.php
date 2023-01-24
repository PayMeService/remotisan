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
$scope.killUuid = null;
$scope.showHistory = false;
$scope.log = {
uuid: null,
content: "",
};

$scope.$watch('showHistory', function(newVal,oldVal){
if(newVal === true) {
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

$scope.locationPath = function (newPath)
{
return $location.path(newPath);
};

$scope.onChangeDropdownValue = function () {
$scope.params = '';
};

$scope.execute = function () {
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
}, function (response) {
console.log(response);
});
};

$scope.getHistory = function(){
$http.get($scope.baseUrl + "/history").then(function(response){
$scope.historyRecords = response.data;
}, function(response){
console.log(response);
});
};

$scope.killProcess = function(uuid){
$scope.killUuid = uuid;
$http.post($scope.baseUrl + "/kill/" + $scope.killUuid)
.then(function(response){
console.log("Response success", response.data);
$scope.killUuid = null;
alert("Process killed");
},function(response){
console.log(response);
alert("Error killing process. see console.");
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

$scope.readLog = function (log_uuid = null) {
$scope.log.uuid = log_uuid !== null ? log_uuid : $scope.log.uuid;
$http.get($scope.baseUrl + "/execute/" + $scope.log.uuid)
.then(function (response) {
$scope.locationPath($scope.log.uuid);
console.log(response.data);
$scope.log.content = response.data.content.join("\n");
if (!response.data.isEnded) {
$timeout( function(){ $scope.readLog(); }, 1000);
}
}, function (response) {
console.log(response);
});
};
}]);
