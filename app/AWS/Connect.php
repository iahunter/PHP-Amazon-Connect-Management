<?php

namespace App\AWS;

use Aws\Connect\ConnectClient;
use Aws\Exception\AwsException; 

class Connect
{

    public function __construct($region, $key, $secret){
        $this->region = $region;
        $this->app_key = $key;
        $this->app_secret = $secret;

        $this->ConnectClient = new ConnectClient([
            'version'     => 'latest',
            'region'      => $this->region,
            'credentials' => [
                'key'    => $this->app_key,
                'secret' => $this->app_secret,
            ],
        ]);
    }

    public function backupStorageConfigs($instance){
        
        $types = [  
                    'CALL_RECORDINGS', 
                    'CONTACT_TRACE_RECORDS',
                    'AGENT_EVENTS',
        ];
        
        $storage = [];

        foreach($types as $type){
            $getresult = $this->ConnectClient->listInstanceStorageConfigs([
                'InstanceId' => $instance['Id'],
                'ResourceType' => $type,
            ]);
            $storage[$type] = $getresult['StorageConfigs']; 
            //print_r($getresult);
        }

        return $storage; 
    }

    public function backupInstanceAttributes($instance){
        // Get Custom Flows and Store in the Database
        $result  = $this->ConnectClient->listInstanceAttributes([
            'InstanceId' => $instance['Id'],
        ]);

        $list = [];

        if(isset($result ['Attributes']) && count($result ['Attributes'])){
            $list = $result ['Attributes'];
        }

        return $list;
    }

    public function backupPhoneNumbers($instance){
        // Get Custom Flows and Store in the Database
        $result  = $this->ConnectClient->listPhoneNumbers([
            'InstanceId' => $instance['Id'],
        ]);

        $list = [];

        if(isset($result ['PhoneNumberSummaryList']) && count($result ['PhoneNumberSummaryList'])){
            $list = $result ['PhoneNumberSummaryList'];
        }

        return $list;
    }

    public function backupContactFlows($instance){
        // Get Custom Flows and Store in the Database
        $flows = $this->ConnectClient->listContactFlows([
            'InstanceId' => $instance['Id'],
        ]);

        
        //Disregard the default and sample flows
        $discard_regex = [
            "/^Default/",
            "/^Sample/",
        ];

        $custom_flows = [];
        if(isset($flows['ContactFlowSummaryList']) && count($flows['ContactFlowSummaryList'])){
            foreach($flows['ContactFlowSummaryList'] as $flow){
                //print_r($flow['Name']);
                $found = false;
                // Discard Default and Sample Flows
                foreach($discard_regex as $regex){
                    if(preg_match($regex,$flow['Name'])){
                        $found = true;
                    }
                }

                if($found == true){
                    continue;
                }else{
                    //print_r($flow);
                }

                // Need to possibly queue these jobs in case they error out. 
                try{
                    $getresult = $this->ConnectClient->describeContactFlow([
                        'ContactFlowId' => $flow['Id'],
                        'InstanceId' => $instance['Id'],
                    ]);
                }catch(AwsException $e){
                    echo 'Caught exception: ',  $e->getMessage(), "\n";
                    echo 'Cannot get contact flow. Moving on.'.PHP_EOL;
                    continue;
                }
                
                
                //print_r($getresult);
                $custom_flows[] = $getresult['ContactFlow'];
            }
        }

        return $custom_flows;
    }


    public function backupUsers($instance){
        // Get Custom Flows and Store in the Database
        $users = $this->ConnectClient->listUsers([
            'InstanceId' => $instance['Id'],
        ]);

        $userlist = [];

        if(isset($users['UserSummaryList']) && count($users['UserSummaryList'])){
            foreach($users['UserSummaryList'] as $user){
                // Need to possibly queue these jobs in case they error out. 
                try{
                    $getresult = $this->ConnectClient->describeUser([
                        'UserId' => $user['Id'],
                        'InstanceId' => $instance['Id'],
                    ]);
                }catch(AwsException $e){
                    echo 'Caught exception: ',  $e->getMessage(), "\n";
                    continue;
                }
                
                
                //print_r($getresult);
                $userlist[] = $getresult['User'];
            }
        }

        return $userlist;
    }


    public function backupUserHierarchyGroups($instance){
        // Get Custom Flows and Store in the Database
        $uhgs = $this->ConnectClient->listUserHierarchyGroups([
            'InstanceId' => $instance['Id'],
        ]);

        $list = [];

        if(isset($uhgs['UserHierarchyGroupSummaryList']) && count($uhgs['UserHierarchyGroupSummaryList'])){
            foreach($uhgs['UserHierarchyGroupSummaryList'] as $i){
                // Need to possibly queue these jobs in case they error out. 
                try{
                    $getresult = $this->ConnectClient->describeUserHierarchyGroup([
                        'HierarchyGroupId' => $i['Id'],
                        'InstanceId' => $instance['Id'],
                    ]);
                }catch(AwsException $e){
                    echo 'Caught exception: ',  $e->getMessage(), "\n";
                    continue;
                }
                
                
                //print_r($getresult);
                $list[] = $getresult['HierarchyGroup'];
            }
        }

        return $list;
    }


    public function backupUserHierarchyStructure($instance){

        try{
            $getresult = $this->ConnectClient->describeUserHierarchyStructure([
                'InstanceId' => $instance['Id'],
            ]);
        }catch(AwsException $e){
            echo 'Caught exception: ',  $e->getMessage(), "\n";
        }
        
        $list = [];
        //print_r($getresult);
        if(isset($getresult['HierarchyStructure']) && count($getresult['HierarchyStructure'])){
            $list[] = $getresult['HierarchyStructure'];
        }
            
        return $list;
    }

    public function backupRoutingProfiles($instance){
        // Get Custom Flows and Store in the Database
        $profiles = $this->ConnectClient->listRoutingProfiles([
            'InstanceId' => $instance['Id'],
        ]);

        $list = [];

        if(isset($profiles['RoutingProfileSummaryList']) && count($profiles['RoutingProfileSummaryList'])){
            foreach($profiles['RoutingProfileSummaryList'] as $i){
                // Need to possibly queue these jobs in case they error out. 
                try{
                    $getresult = $this->ConnectClient->describeRoutingProfile([
                        'RoutingProfileId' => $i['Id'],
                        'InstanceId' => $instance['Id'],
                    ]);
                }catch(AwsException $e){
                    echo 'Caught exception: ',  $e->getMessage(), "\n";
                    continue;
                }

                // Need to possibly queue these jobs in case they error out. 
                try{
                    $queues = $this->backupRoutingProfileQueues($instance, $i['Id']);

                }catch(AwsException $e){
                    echo 'Caught exception: ',  $e->getMessage(), "\n";
                    continue;
                }

                $profiles = $getresult['RoutingProfile']; 
                $profiles['RoutingProfileQueueConfigSummaryList'] = $queues;
                
                //print_r($getresult);
                $list[] = $profiles;
            }
        }

        return $list;
    }

    public function backupSecurityProfiles($instance){
        // Get Custom Flows and Store in the Database
        $profiles = $this->ConnectClient->listRoutingProfiles([
            'InstanceId' => $instance['Id'],
        ]);

        $list = [];

        if(isset($profiles['SecurityProfileSummaryList']) && count($profiles['SecurityProfileSummaryList'])){
            foreach($profiles['SecurityProfileSummaryList'] as $i){
                // Need to possibly queue these jobs in case they error out. 
                try{
                    $getresult = $this->ConnectClient->describeRoutingProfile([
                        'RoutingProfileId' => $i['Id'],
                        'InstanceId' => $instance['Id'],
                    ]);
                }catch(AwsException $e){
                    echo 'Caught exception: ',  $e->getMessage(), "\n";
                    continue;
                }
                
                //print_r($getresult);
                $list[] = $getresult['RoutingProfile'];
            }
        }

        return $list;
    }

    public function backupRoutingProfileQueues($instance, $profileId){
        // Get Custom Flows and Store in the Database
        $profiles = $this->ConnectClient->listRoutingProfileQueues([
            'InstanceId' => $instance['Id'],
            'RoutingProfileId' => $profileId, // REQUIRED
        ]);

        $list = [];

        if(isset($profiles['RoutingProfileQueueConfigSummaryList']) && count($profiles['RoutingProfileQueueConfigSummaryList'])){
            foreach($profiles['RoutingProfileQueueConfigSummaryList'] as $i){
                $list[] = $i;
            }
        }

        return $list;
    }

    public function backupQueues($instance){
        // Get Custom Flows and Store in the Database
        $queues = $this->ConnectClient->listQueues([
            'InstanceId' => $instance['Id'],
        ]);

        $list = [];

        if(isset($queues['QueueSummaryList']) && count($queues['QueueSummaryList'])){
            foreach($queues['QueueSummaryList'] as $i){
                // Need to possibly queue these jobs in case they error out. 
                try{
                    $getresult = $this->ConnectClient->describeQueue([
                        'QueueId' => $i['Id'],
                        'InstanceId' => $instance['Id'],
                    ]);
                }catch(AwsException $e){
                    echo 'Caught exception: ',  $e->getMessage(), "\n";
                    continue;
                }
                
                if($getresult){
                    $quickconnects = $this->ConnectClient->listQueueQuickConnects([
                        'QueueId' => $i['Id'],
                        'InstanceId' => $instance['Id'],
                    ]);
                }

                if(isset($quickconnects['QuickConnectSummaryList'])){
                    $getresult['Queue']['QuickConnectSummaryList'] = $quickconnects['QuickConnectSummaryList']; 
                }
                
                //print_r($getresult);
                $list[] = $getresult['Queue'];
            }
        }

        return $list;
    }

    public function backupHoursOfOperations($instance){
        // Get Custom Flows and Store in the Database
        $hours = $this->ConnectClient->listHoursOfOperations([
            'InstanceId' => $instance['Id'],
        ]);

        $list = [];

        if(isset($hours['HoursOfOperationSummaryList']) && count($hours['HoursOfOperationSummaryList'])){
            foreach($hours['HoursOfOperationSummaryList'] as $i){
                // Need to possibly queue these jobs in case they error out. 
                try{
                    $getresult = $this->ConnectClient->describeHoursOfOperation([
                        'HoursOfOperationId' => $i['Id'],
                        'InstanceId' => $instance['Id'],
                    ]);
                }catch(AwsException $e){
                    echo 'Caught exception: ',  $e->getMessage(), "\n";
                    continue;
                }
                
                
                //print_r($getresult);
                $list[] = $getresult['HoursOfOperation'];
            }
        }

        return $list;
    }


    public function backupQuickConnects($instance){
        // Get Custom Flows and Store in the Database
        $qconnects = $this->ConnectClient->listQuickConnects([
            'InstanceId' => $instance['Id'],
        ]);

        $list = [];

        if(isset($qconnects['QuickConnectSummaryList']) && count($qconnects['QuickConnectSummaryList'])){
            foreach($qconnects['QuickConnectSummaryList'] as $i){
                // Need to possibly queue these jobs in case they error out. 
                try{
                    $getresult = $this->ConnectClient->describeQuickConnect([
                        'QuickConnectId' => $i['Id'],
                        'InstanceId' => $instance['Id'],
                    ]);
                }catch(AwsException $e){
                    echo 'Caught exception: ',  $e->getMessage(), "\n";
                    continue;
                }
                
                
                //print_r($getresult);
                $list[] = $getresult['QuickConnect'];
            }
        }

        return $list;
    }

    public function backupPrompts($instance){

        // Get Custom Flows and Store in the Database
        $prompts = $this->ConnectClient->listPrompts([
            'InstanceId' => $instance['Id'],
        ]);

        $list = [];

        if(isset($prompts['PromptSummaryList']) && count($prompts['PromptSummaryList'])){
            return $prompts['PromptSummaryList']; 
        }
    }


}