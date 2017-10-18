<?php namespace Avram\AwsCronJob;

use Aws\Ec2\Ec2Client;

class Ec2Instance
{
    /** @var string */
    private $connection;

    /**
     * Ec2Instance constructor.
     *
     * @param array $connection
     */
    public function __construct(array $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Return this EC2 instance ID.
     *
     * @return string
     */
    public function thisInstanceId()
    {
        return file_get_contents('http://169.254.169.254/'.$this->connection['version'].'/meta-data/instance-id');
    }

    /**
     * Return IDs of all running EC2 instances for the specified environment.
     *
     * @param string $environment
     *
     * @return array
     */
    public function allInstanceIds($environment)
    {
        $ec2Client = new Ec2Client($this->connection);

        $result          = $ec2Client->DescribeInstances();
        $data            = $result->toArray();
        $allInstances    = [];
        $activeInstances = [];

        foreach ($data['Reservations'] as $reservation) {
            $allInstances = array_merge($allInstances, $reservation['Instances']);
        }

        foreach ($allInstances as $instance) {
            if ($instance['State']['Name'] != 'running') {
                continue;
            }

            $groupName = $this->getInstanceTag('Name', $instance);
            if ($groupName != $environment) {
                continue;
            }

            $activeInstances[] = $instance['InstanceId'];
        }

        sort($activeInstances);

        return $activeInstances;
    }

    /**
     * Check if current EC2 instance is the leader of the group.
     *
     * @param $environment
     *
     * @return bool
     */
    public function isLeader($environment)
    {
        return $this->thisInstanceId() == $this->allInstanceIds($environment)[0];
    }

    /**
     * Get EC2 instance tag by name
     *
     * @param string $tagName
     * @param array  $instance
     *
     * @return null
     */
    protected function getInstanceTag($tagName, array $instance)
    {
        foreach ($instance['Tags'] as $tag) {
            if ($tag['Key'] == $tagName) {
                return $tag['Value'];
            }
        }

        return null;
    }
}