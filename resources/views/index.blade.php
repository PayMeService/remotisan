<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>
        Remotisan
    </title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space%20Mono%3Aital%2Cwght%400%2C400&directory=3&display=block">
    <script src="https://ajax.googleapis.com/ajax/libs/angularjs/1.8.2/angular.min.js"></script>
</head>
<body ng-app="RemotisanApp">

<div class="container" id="container" ng-controller="RemotisanController">
    <h2>Commands</h2>
    <form class="form-inline" ng-submit="execute()" ng-init='init("{{ config('remotisan.url') }}")'>
        <label class="my-1 mr-2" for="inlineFormCustomSelectPref">Preference</label>
        <select required class="custom-select my-1 mr-sm-2" ng-model="command" name="command"
                ng-options='c.name as (c.name + " - " + c.description) for c in commands' ng-change="onChangeDropdownValue()">
        </select>

        <textarea placeholder="input options & arguments (if required)..." name="command_arguments" ng-model="command_arguments" style="width:70%"></textarea>

        <input type="button" class="btn btn-primary" ng-click="execute()" value="Execute" />

        <hr style="opacity:0; display:block; width:100%;"/>

        <div id="command_details_wrapper" ng-show="command !== null" ng-model="command_details">
            <div class="abc" style="background-color: #f9fdf0">
                <div><strong>Command name:</strong> @{{command_details.name}}</div>
                <div><strong>Description:</strong> @{{command_details.description}}</div>
                <div><strong>Help:</strong> @{{command_details.help}}</div>
                <div><strong>Arguments:</strong></div>
                <div style="margin-left:20px;" ng-repeat="(field_name, field_details) in command_details['definition']['args']">
                    <div><strong>@{{field_name}}:</strong> @{{field_details}}</div>
                </div>
                <div><strong>Options:</strong></div>
                <div style="margin-left:20px;" ng-repeat="(field_name, field_details) in command_details['definition']['ops']">
                    <div><strong>@{{field_name}}:</strong> @{{field_details}}</div>
                </div>
            </div>
        </div>
    </form>

    <h2>Logger</h2>
    <pre style="width: 90%; background-color: black; color: darkcyan;font-family: 'Space Mono', sans-serif;">@{{ log.content }}</pre>
</div>
</body>
<script>
    angular.module('RemotisanApp', [])
        .controller('RemotisanController', ["$scope", "$http", "$timeout", "$sce", "$location", function($scope, $http, $timeout, $sce, $location) {
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
</script>
<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
</html>
