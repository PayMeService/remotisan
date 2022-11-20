@if(!($ngApp ?? null))
    var RemotisanApp = angular.module('RemotisanApp', []);
@endif

{{ $ngApp ?? "RemotisanApp" }}.controller('RemotisanController', ["$scope", "$http", "$timeout", "$sce", "$location", function($scope, $http, $timeout, $sce, $location) {
$scope.baseUrl = '';
$scope.commands = [];
$scope.command = null;
$scope.command_arguments = null;
$scope.command_details = [];
$scope.params = null;
$scope.$location = {};
$scope.log = {
uuid: null,
content: "",
}
$scope.init = function(baseUrl) {
$scope.baseUrl = baseUrl;
$scope.fetchCommands();
if($location.path() != '') {
$scope.uuid = $location.path().replace('/', '');
$scope.readLog();
}
}

$scope.locationPath = function (newPath)
{
return $location.path(newPath);
}

$scope.onChangeDropdownValue = function () {
$scope.command_arguments = '';
$scope.command_details = $scope.commands[$scope.command];
}

$scope.execute = function () {
$http.post($scope.baseUrl + "/execute", {
command: $scope.command,
command_arguments: $scope.command_arguments,
params: $scope.params
}).then(function (response) {
$scope.uuid = response.data.id;

$timeout( function(){ $scope.readLog(); }, 5000);
}, function (response) {
console.log(response);
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
$http.get($scope.baseUrl + "/execute/" + $scope.uuid)
.then(function (response) {
$scope.locationPath($scope.uuid);
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
