
<div class="container" id="container" ng-app="RemotisanApp" ng-controller="RemotisanController">
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
