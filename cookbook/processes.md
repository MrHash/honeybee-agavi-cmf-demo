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
