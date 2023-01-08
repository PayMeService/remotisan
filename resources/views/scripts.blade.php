@if(!($ngApp ?? null))
    var RemotisanApp = angular.module('RemotisanApp', []);
@endif

{{ $ngApp ?? "RemotisanApp" }}.controller('RemotisanController', ["$scope", "$http", "$timeout", "$sce", "$location", function($scope, $http, $timeout, $sce, $location) {
$scope.baseUrl = '';
$scope.commands = [];
$scope.history = [];
$scope.command = null;
$scope.params = '';
$scope.$location = {};
$scope.killPid = null;
$scope.log = {
uuid: null,
content: "",
}
$scope.init = function(baseUrl) {
$scope.baseUrl = baseUrl;
$scope.fetchCommands();
if($location.path() != '') {
$scope.log.uuid = $location.path().replace('/', '');
$scope.readLog();
}
}

$scope.locationPath = function (newPath)
{
return $location.path(newPath);
}

$scope.onChangeDropdownValue = function () {
$scope.params = '';
}

$scope.execute = function () {
$http.post($scope.baseUrl + "/execute", {
command: $scope.command,
params: $scope.params
}).then(function (response) {
$scope.log.uuid = response.data.id;

$timeout( function(){ $scope.readLog(); }, 5000);
}, function (response) {
console.log(response);
});
}

$scope.getHistory = function(){
    $http.get($scope.baseUrl + "/history").then(function(response){
        $scope.history = response.data.history;
    }, function(response){
        console.log(response);
    });
}

$scope.killRun = function(){
    $http.get($scope.baseUrl + "/kill" + $scope.killPid).then(function(response){
        // render result.
        console.log("Response success", response);
        $scope.killPid = null;
    },function(response){
        console.log(response);
        // offer to retry request to kill.
    });
}

$scope.fetchCommands = function () {
$http.get($scope.baseUrl + "/commands")
.then(function (response) {
$scope.commands = response.data.commands;
}, function (response) {
console.log(response);
});
}

$scope.readLog = function () {
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
}
}]);
