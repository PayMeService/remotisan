<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space%20Mono%3Aital%2Cwght%400%2C400&directory=3&display=block">

<div class="container" id="container" data-ng-app="RemotisanApp" data-ng-controller="RemotisanController">
    <h2>Commands</h2>
    <form class="form-inline" data-ng-submit="execute()" data-ng-init='init("{{ config('remotisan.url') }}")'>
        <div>
            <label class="my-1 mr-2" for="command">Preference</label>
            <select required class="custom-select my-1 mr-sm-2" data-ng-model="command" id="command"
                    data-ng-options='c.name as (c.name + " - " + c.description) for c in commands' data-ng-change="onChangeDropdownValue()">
            </select>
            <input type="checkbox" id="show_help_checkbox" name="show_help_checkbox" data-ng-model="showHelp">
            <label for="show_help_checkbox">
                <span data-ng-show="!showHelp">Show commands help</span>
                <span data-ng-show="showHelp">Hide commands help</span>
            </label>
        </div>
        <div>
            <textarea placeholder="input options & arguments (if required)... Max length 1000 chars" name="params" maxlength="1000" data-ng-model="params" style="width:70%"></textarea>

            <input type="button" data-ng-disabled="!showExecButton" class="btn btn-primary" data-ng-click="execute()" value="Execute" />
            <span data-ng-show="!showExecButton" class="fa fa-spinner fa-spin" style="margin-left: 15px"></span>
        </div>
        <hr style="opacity:0; display:block; width:100%;"/>

        <div data-ng-show="command && showHelp">
            <div class="abc" style="background-color: #f9fdf0">
                <div><strong>Command name:</strong> @{{commands[command].name}}</div>
                <div><strong>Description:</strong> @{{commands[command].description}}</div>
                <div><strong>Help:</strong> @{{commands[command].help}}</div>
                <div><strong>Arguments:</strong></div>
                <div style="margin-left:20px;" data-ng-repeat="(field_name, field_details) in commands[command]['definition']['args']">
                    <div><strong>@{{field_name}}:</strong> @{{field_details}}</div>
                </div>
                <div><strong>Options:</strong></div>
                <div style="margin-left:20px;" data-ng-repeat="(field_name, field_details) in commands[command]['definition']['ops']">
                    <div><strong>@{{field_name}}:</strong> @{{field_details}}</div>
                </div>
            </div>
        </div>
    </form>

    <div class="history-wrapper"> <!-- show when history button clicked! -->
        <button title="show-hide history" data-ng-click="showHistory = !showHistory;"><span data-ng-hide="!showHistory">Hide</span><span data-ng-hide="showHistory">Show</span> History</button>
        <div data-ng-show="showHistory">
            <br>
            <label class="my-1 mr-2" for="user">Select User</label>
            <select required class="custom-select my-1 mr-sm-2" data-ng-model="user" id="user"
                    data-ng-options='user.key as user.name for user in users track by user.key'
                    data-ng-change="refreshHistoryIfNeeded()">
            </select>
            <br>
            <div style="display: ruby;">
                <label for="searchable">Command:</label>
                <input type="search" class="form-control input-sm" id="searchable" data-ng-model="searchable" style="width: auto;" maxlength="100"/>
                <button class="btn btn-primary" data-ng-click="refreshHistoryIfNeeded()">Filter</button>
            </div>
            <br>
            <table class="table table-bordered">
                <thead class="thead-dark">
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Command</th>
                    <th>UUID</th>
                    <th>Proc Status</th>
                    <th>Date</th>
                    <th>Finished</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody class="table-striped table-hover">
                <tr data-ng-repeat="(key, log_data) in historyRecords"> <!-- foreach loop -->
                    <td>@{{log_data.id}}</td>
                    <td>@{{log_data.user_identifier}}</td>
                    <td style="max-width:700px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis" title="@{{log_data.command}} @{{log_data.parameters}}">
                        <button  class="fa fa-clipboard" data-ng-click="copyToClipboard(log_data.parameters)"></button>
                        @{{log_data.command}} @{{log_data.parameters}}
                    </td>
                    <td><span data-ng-click="readLog(log_data.job_uuid)" class="label label-info" style="cursor: pointer;">@{{log_data.job_uuid}}</span></td><!-- use same call as showing log. -->
                    <td >
                        <span data-ng-if="log_data.process_status == 1"  class="label label-primary">@{{statusCodeToHumanReadable(log_data.process_status)}} @{{showKilledByIfStatusKilled(log_data)}}</span>
                        <span data-ng-if="log_data.process_status == 2"  class="label label-success">@{{statusCodeToHumanReadable(log_data.process_status)}} @{{showKilledByIfStatusKilled(log_data)}}</span>
                        <span data-ng-if="log_data.process_status != 1 && log_data.process_status != 2"  class="label label-danger">@{{statusCodeToHumanReadable(log_data.process_status)}} @{{showKilledByIfStatusKilled(log_data)}}</span>
                    </td>
                    <td>@{{log_data.executed_at*1000 | date: 'yyyy-MM-dd HH:mm:ss'}}</td>
                    <td>@{{ log_data.finished_at ? (log_data.finished_at*1000 | date: 'yyyy-MM-dd HH:mm:ss') : '' }}</td>
                    <td>
                        <span data-ng-if="log_data.process_status == 1" data-ng-click="killProcess(log_data.job_uuid)" class="label label-danger" style="cursor: pointer;">Kill Process</span><!-- set history data (the pid) -->
                        <span data-ng-click="reRun(log_data.command, log_data.parameters)" class="label label-info" style="cursor: pointer;">Re-Run</span>
                    </td>
                </tr>
                </tbody>
                <tfoot>
                <tr>
                    <td>#</td>
                    <td>User</td>
                    <td>Command</td>
                    <td>UUID</td>
                    <td>Proc Status</td>
                    <td>Date</td>
                    <td>Finished</td>
                    <td>Actions</td>
                </tr>
                </tfoot>
            </table>

            <div class="pagination">
                <button data-ng-repeat="link in historyPagination" class="btn"
                        ng-click="getHistoryFromFullLink(link.url)"
                        ng-class="{ 'btn-primary' : link.active, 'btn-default': !link.active }"
                        ng-disabled="!link.url"
                >@{{ link.label }} </button>
            </div>

        </div>
    </div>

    <h2>Logger</h2>

    <div id="terminal"></div>
    <script>
        var term = new Terminal({cols: 182});
        term.open(document.getElementById('terminal'));
    </script>
</div>
