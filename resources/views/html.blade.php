<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space%20Mono%3Aital%2Cwght%400%2C400&directory=3&display=block">

<div class="container" id="container" data-ng-app="RemotisanApp" data-ng-controller="RemotisanController">
    <h2>Commands</h2>
    <form class="form-inline" data-ng-submit="execute()" data-ng-init='init("{{ config('remotisan.url') }}")'>
        <label class="my-1 mr-2" for="inlineFormCustomSelectPref">Preference</label>
        <select required class="custom-select my-1 mr-sm-2" data-ng-model="command" name="command"
                data-ng-options='c.name as (c.name + " - " + c.description) for c in commands' data-ng-change="onChangeDropdownValue()">
        </select>

        <textarea placeholder="input options & arguments (if required)..." name="params" data-ng-model="params" style="width:70%"></textarea>

        <input type="button" class="btn btn-primary" data-ng-click="execute()" value="Execute" />

        <hr style="opacity:0; display:block; width:100%;"/>

        <div data-ng-show="command">
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

        <div data-ng-show="history"> <!-- show when history button clicked! -->
            <div>
                <table class="">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Command</th>
                            <th>UUID</th>
                            <th>PID</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr> <!-- foreach loop -->
                            <td></td>
                            <td></td>
                            <td></td>
                            <td><span data-ng-click="showLog(UUID)">UUID</span></td><!-- set history data (the uuid) -->
                            <td></td>
                            <td></td>
                            <td>
                                <span data-ng-click="kill(PID)">Kill Process</span><!-- set history data (the pid) -->
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>ID</td>
                            <td>User</td>
                            <td>Command</td>
                            <td>UUID</td>
                            <td>PID</td>
                            <td>Date</td>
                            <td>Actions</td>
                        </tr>
                    </tfoot>
                </table>
                <!-- put historic table -->
                <!-- in table, add the KILL button and retry on kill response error -->
            </div>
        </div>
    </form>

    <h2>Logger</h2>
    <pre style="width: 90%; background-color: black; color: darkcyan;font-family: 'Space Mono', sans-serif;">@{{ log.content }}</pre>
</div>
