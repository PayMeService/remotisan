@if(!($ngApp ?? null))
    var RemotisanApp = angular.module('RemotisanApp', []);
@endif

{{ $ngApp ?? "RemotisanApp" }}.controller('RemotisanController', ["$scope", "$http", "$timeout", "$sce", "$location", function($scope, $http, $timeout, $sce, $location) {
$scope.baseUrl = '';
$scope.commands = [];
$scope.users = [];
$scope.user = null;
$scope.searchable = '';
$scope.historyRecords = [];
$scope.command = null;
$scope.params = '';
$scope.bulkParams = '';
$scope.$location = {};
$scope.showHistory = false;
$scope.showExecButton = true;
$scope.showHelp = false;
$scope.mode = 'single';
$scope.page = 1;
$scope.log = {
uuid: null,
content: "",
};
$scope.errorMessages = [];
$scope.proc_statuses = {
"1": "RUNNING",
"2": "COMPLETED",
"3": "FAILED",
"4": "KILLED"
};

$scope.$watch('showHistory', function(newVal, oldVal) {
if (newVal) {
$scope.getHistory();
}
}, true);

$scope.init = function(baseUrl) {
$scope.baseUrl = baseUrl;
$scope.fetchCommands();
$scope.fetchFiltersData();
if ($location.path() != '') {
$scope.log.uuid = $location.path().replace('/', '');
$scope.readLog();
}
};

$scope.statusCodeToHumanReadable = function(status_code) {
return $scope.proc_statuses[status_code];
}

$scope.showKilledByIfStatusKilled = function(recordData) {
if (recordData.process_status === 4) { // proc_statuses 4 = killed.
return "(" + recordData.killed_by + ")";
}
return "";
}

$scope.locationPath = function(newPath) {
return $location.path(newPath);
}

$scope.onChangeDropdownValue = function() {
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

$scope.execute = function() {
$scope.resetLog();
$scope.errorMessages = [];
$scope.lockExecButton();
let logIds = [];
let attemptedCount = 0;
let commandCount = $scope.mode === 'single' ? 1 : $scope.bulkParams.split('\n').filter(cmd => cmd.trim()).length;

let completeExecution = function() {
    $scope.log.uuid = logIds[logIds.length - 1];
    $scope.readLog($scope.log.uuid);
    $scope.refreshHistoryIfNeeded();
    if ($scope.errorMessages.length) {
        alert("Some commands failed to execute:\n" + $scope.errorMessages.join('\n'));
    }
    $scope.unlockExecButton();
};

let onCommandFinished = function(logId, errorMessage) {
    attemptedCount++;
    if (logId) {
        logIds.push(logId);
    } else {
        $scope.errorMessages.push(errorMessage);
    }
    if (attemptedCount === commandCount) {
        completeExecution();
    }
};

if ($scope.mode === 'single') {
    $scope.executeCommand($scope.command, $scope.params, onCommandFinished);
} else if ($scope.mode === 'bulk') {
    let commands = $scope.bulkParams.split('\n');
    commands.forEach((cmd) => {
        let trimmedCmd = cmd.trim();
        if (trimmedCmd) {
            $scope.executeCommand(trimmedCmd, '', onCommandFinished);
        }
    });
}
};

$scope.executeCommand = function(command, params, callback) {
$http.post($scope.baseUrl + "/execute", {
command: command,
params: params
}).then(function(response) {
callback(response.data.id, null);
}, function(response) {
callback(null, command);
$scope.refreshHistoryIfNeeded();
});
};

$scope.refreshHistoryIfNeeded = function() {
if ($scope.showHistory) { $scope.getHistory(); }
}

$scope.getHistoryFromFullLink = function(fullLink) {
$scope.getHistory(fullLink.split("?page=")[1]);
}

$scope.getHistory = function(page) {
$scope.page = page || $scope.page;
var filters = new URLSearchParams({
    page: $scope.page,
    user: $scope.user,
    command: $scope.searchable
}).toString()

$http.get($scope.baseUrl + "/history?" + filters).then(function(response) {
$scope.historyRecords = response.data.data;
linksLength = response.data.links.length;
response.data.links[0].label = "Previous";
response.data.links[linksLength - 1].label = "Next";
$scope.historyPagination = response.data.links;
}, function(response) {
console.log(response);
});
};

$scope.killProcess = function(uuid) {
if (!confirm("Do you really want to kill the job " + uuid + " ?")) { return; }
$http.post($scope.baseUrl + "/kill/" + uuid)
.then(function(response) {
console.log("Response success", response.data);
$scope.refreshHistoryIfNeeded();
alert("Process killed");
}, function(response) {
console.log(response);
var alertInfo = "";
if (response.status == 409) {
    alertInfo = "Kill already in progress";
} else if (response.status == 422) {
    alertInfo = "Process already killed";
} else if (response.status == 401) {
    alertInfo = "Not allowed!";
} else {
    alertInfo = "Server Error";
}
alert(alertInfo);
});
};

$scope.fetchCommands = function() {
$http.get($scope.baseUrl + "/commands")
.then(function(response) {
$scope.commands = response.data.commands;
}, function(response) {
console.log(response);
});
};

$scope.fetchFiltersData = function() {
$http.get($scope.baseUrl + "/filters").then(function(response) {
$scope.users = [...response.data.users.map(item => ({
    key: item,
    name: item
})), {
    key: "null",
    name: "All"
}];
console.log("Users:");
console.log($scope.users);
}, function(response) {
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

$scope.readLog = function(log_uuid = null) {
$scope.log.uuid = log_uuid || $scope.log.uuid;
$http.get($scope.baseUrl + "/execute/" + $scope.log.uuid)
.then(function(response) {
$scope.locationPath($scope.log.uuid);
console.log(response.data);
term.clear();
response.data.content.forEach((line) => term.writeln(line));
if (!response.data.isEnded) {
    $timeout(function() {
        $scope.readLog();
    }, 1000);}

        $scope.unlockExecButton();

}, function(response) {
console.log(response);
$scope.unlockExecButton();
});
};
}]);
