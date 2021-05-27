<?php

namespace App\Azure;

class Policy
{
    public function __construct()
    {
    }


    public function createClaimsPolicy($array, $name)
    {

    $definition = [
        "ClaimsMappingPolicy" => [
            "Version"               => 1,
            "IncludeBasicClaimSet"  => "true",
            "ClaimsSchema"          => $array,
            "displayName"           => $name,
            "isOrganizationDefault" => false,
        ]
    ]

    $headers = [
        'content-type' => 'claimsMappingPolicies/json',
    ];
    
    $command = 'az rest'
    . ' --method post '
    . ' --headers \''.json_encode($headers).'\''
    . ' --uri "https://graph.microsoft.com/v1.0/policies/claimsMappingPolicies'.'"'
    . ' --body \''.json_encode($array).'\'';
    
    print $command;
    
    $output = shell_exec($command);

    return $output;
    }



/* Putting crap code from Azure CLI integration that never got working.... couldn't get claims to work. 
    
        // Testing Azure CLI  

        $command = 'az ad sp list --display-name "kss"';
        $output = shell_exec($command);

        // Parse json
        $azureapps = json_decode($output, true);

        $applist = [];
        foreach($azureapps as $app){
            $applist[] = $app['displayName'];
        }

        $choice = $this->choice('What App Name do you want for admins?', $applist); 

        foreach($azureapps as $app){
            if($app['displayName'] == $choice){
                print_r($app);
                //$id = $app['appId'];
                $spId = $app['objectId'];
            }
        }

        $command = "az ad sp show --id $spId";
        $output = shell_exec($command);

        // Parse json
        $azureapp = json_decode($output, true);

        print_r($azureapp);


        $type = "agents";

        $this->instance_id = $this->instance['Id'];
        $this->instance_id = $this->instance['Id'];

        echo "#########################################################".PHP_EOL;
        echo "  Edit SAML Config for $type in Azure AD enterprise App  ".PHP_EOL;
        echo "#########################################################".PHP_EOL;

        if($type == 'admins'){
            $relayState = "https://$this->region.console.aws.amazon.com/connect/federate/$this->instance_id?destination=%2Fconnect%2F";
        }elseif($type == 'agents')
        {
            $relayState = "https://$this->region.console.aws.amazon.com/connect/federate/$this->instance_id?destination=%2Fconnect%2Fccp";
        }else{
            print "Unrecognized Type"; 
            return;
        }



        $array_update = [
            "preferredSingleSignOnMode" => "saml",
            "samlSingleSignOnSettings" => [
            "relayState" => $relayState,
            ],
        ];

        //$json = json_encode($array_update,JSON_UNESCAPED_SLASHES);
        //$json = json_encode($array_update,JSON_UNESCAPED_SLASHES);
        //$url = url_encode($array_update);
        
        $command = "az rest --method get --headers content-type=application/json --url https://graph.microsoft.com/v1.0/servicePrincipals/$spId";
        print $command;

        //$command = "az rest --method get --url https://graph.microsoft.com/v1.0/applications/$id";
        //$output = shell_exec($command);

        //print_r($output);

        $headers = [
            'content-type' => 'application/json',
        ];


        // Update the Service Principal for SAML
        $command = 'az rest'
                 . ' --method patch '
                 . ' --headers \''.json_encode($headers).'\''
                 . ' --uri "https://graph.microsoft.com/v1.0/servicePrincipals/' . $spId . '"'
                 . ' --body \''.json_encode($array_update).'\'';

        print $command;

        $output = shell_exec($command);

        print_r($output);




        
        //POST https://graph.microsoft.com/v1.0/policies/claimsMappingPolicies
        //Content-type: claimsMappingPolicies/json


        $claimName = "KSS Connect Claims"; 
        $json = <<<END
{
  "definition": [
    "{\"ClaimsMappingPolicy\":{\"Version\":1,\"IncludeBasicClaimSet\":\"true\", \"ClaimsSchema\": [{\"Source\":\"user\",\"ID\":\"assignedroles\",\"SamlClaimType\": \"https://aws.amazon.com/SAML/Attributes/Role\"}, {\"Source\":\"user\",\"ID\":\"userprincipalname\",\"SamlClaimType\": \"https://aws.amazon.com/SAML/Attributes/RoleSessionName\"}, {\"Source\":\"user\",\"ID\":\"900\",\"SamlClaimType\": \"https://aws.amazon.com/SAML/Attributes/SessionDuration\"}, {\"Source\":\"user\",\"ID\":\"assignedroles\",\"SamlClaimType\": \"appRoles\"}, {\"Source\":\"user\",\"ID\":\"userprincipalname\",\"SamlClaimType\": \"https://aws.amazon.com/SAML/Attributes/nameidentifier\"}]}}"
    ],
  "displayName": $claimName,
  "isOrganizationDefault": false
}
END;

$headers = [
    'content-type' => 'application/json',
];

$command = 'az rest'
. ' --method post '
. ' --headers \''.json_encode($headers).'\''
. ' --uri "https://graph.microsoft.com/v1.0/policies/claimsMappingPolicies'.'"'
. ' --body \''.$json.'\'';


print $command;

$output = shell_exec($command);

print_r($output);

$claimPolicyId = $output['id'];


//$claimPolicyId = "d43567a5-cfad-4e4a-b9d6-6270i775a819";

$json = <<<END
{
    "@odata.id":"https://graph.microsoft.com/v1.0/policies/claimsMappingPolicies/$claimPolicyId"
}
END;

$headers = [
    'content-type' => 'application/json',
];

// List Policies
$command = 'az rest'
. ' --method get '
. ' --headers \''.json_encode($headers).'\''
. ' --uri "https://graph.microsoft.com/v1.0/policies/claimsMappingPolicies'.'"';

print $command;

$output = shell_exec($command);

$json = json_decode($output);

print_r($json);

$claims = $json->value;
$claimPolicyId = null;
foreach($claims as $claim){
    if($claim->displayName == $claimName){
        $claimPolicyId = $claim->id; 
        "Found Claim Name: $claimName"; 
        break;
    }
}

if($claimPolicyId){
    print "Found Claim ID: ".$claimPolicyId.PHP_EOL;
}


/Associate the claims to the servicePrincipal
//POST https://graph.microsoft.com/v1.0/servicePrincipals/a750f6cf-2319-464a-bcc3-456926736a91/claimsMappingPolicies/$ref
//Content-type: claimsMappingPolicies/json


$json = <<<END
{
  "@odata.id":"https://graph.microsoft.com/v1.0/policies/claimsMappingPolicies/$claimPolicyId"
}
END;

$headers = [
    'content-type' => 'application/json',
];

$command = 'az rest'
. ' --method post '
. ' --headers \''.json_encode($headers).'\''
. ' --uri "https://graph.microsoft.com/v1.0/servicePrincipals/'.$spId.'/claimsMappingPolicies/$ref'.'"'
. ' --body \''.$json.'\'';

print $command;

$output = shell_exec($command);

print_r($output);

        return;
}
