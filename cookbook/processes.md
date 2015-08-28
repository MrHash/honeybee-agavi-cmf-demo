#Processes
Processes are configurable state machine driven operations that are performed according to a set of conditions. They are typicall triggered by certain events occurring in the system. 

So far we have already [configured an event handler](./event_handling.md) to synchronise referenced entity data in our projections. This is sufficient for mirroring values but in the case of state changes, they are not cascaded to the related entities. This would mean that deleting an *owner* would not mark her *topics* as deleted, and the *owner* reference does not exist in the *topic* so they are not changed at all. In order to handle this we will configure a process to propagate workflow state changes.

We start by writing a process state machine to define how to handle entity state propagation.

######process/state_propagation.xml
```xml
<?xml version="1.0" encoding="UTF-8" ?>
<!DOCTYPE shortcuts [
    <!ENTITY propagate_state_transition 'HBDemo\Commenting\StateMachine\PropagateStateTransition'>
]>
<state_machines xmlns="urn:schemas-workflux:statemachine:0.5.0">
    <state_machine name="state_propagation_process">
        <!-- 
            Initial state which evaluates the incoming event state change and 
            proceeds to the appropriate next or final state.
        -->
        <initial name="check_origin_state">
            <transition target="propagate_state">
                <guard class="Workflux\Guard\VariableGuard">
                    <option name="expression">origin_state == "deleted"</option>
                </guard>
            </transition>
            <transition target="state_transition_ignored">
                <guard class="Workflux\Guard\VariableGuard">
                    <option name="expression">origin_state != "deleted"</option>
                </guard>
            </transition>
        </initial>

        <!-- 
            This state executes our custom state class and provides mappings to 
            determine how to act on the current entity given the incoming state change.
        -->
        <state name="propagate_state" class="&propagate_state_transition;">
            <!--
                After processing the state will transition to a final state.
            -->
            <transition target="state_transition_propagated" />
            
            <!--
                e.g. if the entity undergoing processing is an *Account* and the 
                state of the incoming event is 'deleted' then we proceed execute
                'delete' event on the account.
            -->
            <option name="transition_map">
                <option name="hbdemo.commenting.account">
                    <option name="deleted">delete</option>
                </option>
                <option name="hbdemo.commenting.topic">
                    <option name="deleted">delete</option>
                </option>
                <option name="hbdemo.commenting.comment">
                    <option name="deleted">delete</option>
                </option>
            </option>
            
            <!--
                For convenience the command map specifies the command to create if 
                the workflow state needs to be proceeded.
            -->
            <option name="command_map">
                <option name="hbdemo.commenting.account">
                    HBDemo\Commenting\Account\Model\Task\ProceedAccountWorkflow\ProceedAccountWorkflowCommand
                </option>
                <option name="hbdemo.commenting.topic">
                    HBDemo\Commenting\Topic\Model\Task\ProceedTopicWorkflow\ProceedTopicWorkflowCommand
                </option>
                <option name="hbdemo.commenting.comment">
                    HBDemo\Commenting\Comment\Model\Task\ProceedCommentWorkflow\ProceedCommentWorkflowCommand
                </option>
            </option>
        </state>

        <final name="state_transition_propagated" />
        <final name="state_transition_ignored" />
    </state_machine>
</state_machines>

```

This state machine configuration handles the `deleted` state propagation for all projections. Now we make the process globally available across the application by adding the following snippet to our services configuration.

######app/config/services.xml
```xml
<ae:configuration>
    <service_map>
        <service_definitions>
            <service name="honeybee.infrastructure.process_map">
                <class>Honeybee\Infrastructure\ProcessManager\ProcessMap</class>
                <provisioner>
                    <class>Honeybee\FrameworkBinding\Agavi\Provisioner\ProcessMapProvisioner</class>
                    <settings>
                        <setting name="processes">
                            <setting name="state_propagation_process">
                                <setting name="state_machine_builder">Honeybee\Infrastructure\ProcessManager\StateMachine\StateMachineBuilder</setting>
                                <setting name="builder_settings">
                                    <setting name="state_machine_definition">%core.modules_dir%/HBDemo_Commenting/process/state_propagation.xml</setting>
                                    <setting name="name">state_propagation_process</setting>
                                </setting>
                            </setting>
                        </setting>
                    </settings>
                </provisioner>
            </service>
        </service_definitions>
    </service_map>
</ae:configuration>
```

Now we can hook up the event handler for each resource to use this process.

######config/Account/events.xml
```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE shortcuts [
    <!ENTITY hbdemo_commenting_account_related_entity_events
    'event.getType() matches "/^hbdemo\.commenting\.owner\..*/"'>
]>
<ae:configurations
    xmlns:ae="http://agavi.org/agavi/config/global/envelope/1.0"
    xmlns="http://berlinonline.de/schemas/honeybee/config/event_bus/1.0"
    xmlns:env="http://berlinonline.de/schemas/honeybee/config/envelope/definition/1.0"
    xmlns:xi="http://www.w3.org/2001/XInclude"
>
    <ae:configuration>
        <event_bus>
            <channels>
                <channel name="honeybee.events.domain">
                    <subscriptions>
                        <subscription enabled="true">
                            <transport>sync</transport>
                            <filter>
                                <setting name="expression">&hbdemo_commenting_account_related_entity_events;</setting>
                            </filter>
                            <handler implementor="\HBDemo\Commenting\StateMachine\RelationStateProjectionProcessor">
                                <setting name="projection_type">hbdemo.commenting.account</setting>
                                <setting name="process_name">state_propagation_process</setting>
                            </handler>
                        </subscription>
                    </subscriptions>
                </channel>

                <channel name="honeybee.events.replay">
                    <subscription>
                        <transport>sync</transport>
                        <filter>
                            <setting name="expression">&hbdemo_commenting_account_related_entity_events;</setting>
                        </filter>
                        <handler implementor="\Honeybee\Projection\EventHandler\RelationProjectionUpdater">
                            <setting name="projection_type">hbdemo.commenting.account</setting>
                        </handler>
                    </subscription>
                </channel>
            </channels>
        </event_bus>
    </ae:configuration>

</ae:configurations>
```

Compared to the event handling configuration based on the `RelationProjectionUpdater`, the difference here is we specify a `RelationStateProjectionProcessor` which executes the process `state_propagation_process`.
