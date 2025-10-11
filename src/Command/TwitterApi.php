<?php
namespace App\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Abraham\TwitterOAuth\TwitterOAuth;


#[AsCommand(name: 'twitter')]
class TwitterBoatCommand extends Command
{
    private $consumer_key;
    private $consumer_secret;
    private $access_token;
    private $access_token_secret;
    private $auth;
    private $output;
    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
        $this->consumer_key = $_ENV['consumer_key'] ; 
        $this->consumer_secret = $_ENV['consumer_secret']  ;
        $this->access_token = $_ENV['access_token']  ;
        $this->access_token_secret = $_ENV['access_token_secret']  ;
        $this->auth = new  \Abraham\TwitterOAuth\TwitterOAuth($this->consumer_key, $this->consumer_secret, $this->access_token, $this->access_token_secret);
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('add-twit',null,InputOption::VALUE_OPTIONAL,'');
        $this->addOption('unfollow',null,InputOption::VALUE_OPTIONAL,'');
        $this->addOption('search-tweet',null,InputOption::VALUE_OPTIONAL,'');
        $this->addOption('favorite-tweet',null,InputOption::VALUE_OPTIONAL,'');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {   
       
        if($input->getOption('add-twit')){
            $createTweet = $this->createTweet($input->getOption('add-twit'));
            $output->writeln($createTweet);
        }
        if($input->getOption('unfollow') == 1){
            $this->unfollowDestroy();
        }
        if($input->getOption('search-tweet')){
            $this->searchCreateFriends($input->getOption('create-user'));
        }

        if($input->getOption('favorite-tweet')){
            $this->tweetFavorite($input->getOption('favorite-tweet'));
        }
        return Command::SUCCESS;
    }

    public function createTweet(string $string){
        $tweet =(array)  $this->auth->post('statuses/update',array('status'=>$string));
        sleep(0.5);
        $message = isset($tweet['created_at']) ? '<fg=green;options=bold>Twitiniz atılmıştır</>' : '<fg=red;options=bold>Üzgünüm Twitiniz atılamadı</>';
        return $message;
        
    }
    public function unfollowDestroy(){
        $friends = (array)  $this->auth->get('friends/ids',array('screen_name'=>"username"));
        sleep(0.5);
        $followers =(array) $this->auth->get('followers/ids',array('screen_name'=>"username"));
        sleep(0.5);
        $unfollowList = array_diff($friends['ids'],$followers['ids']);
        $deleteCount = 0;
        foreach($unfollowList  as $value){
            $unfollowUser =  (array) $this->auth->post('friendships/destroy',array('user_id'=>$value));
            echo $deleteCount. '-'. $unfollowUser['screen_name'].' kullanıcı takibi bırakıldı'."\n";
            $deleteCount ++ ;
            if($deleteCount == 50){
                break;
            }
            sleep(1);
        }
    }
    public function sendDirectMessage($message,$recipient_id){
        $data = [
            'event' => [
                'type' => 'message_create',
                'message_create' => [
                    'target' => [
                        'recipient_id' => $recipient_id
                    ],
                    'message_data' => [
                      'text' =>  $message,
                    ],
                ],
            ],
        ];
        $dm  = (array)  $this->auth->post('direct_messages/events/new',$data,true);
        $message = isset($dm['created_timestamp']) ? '<fg=green;options=bold>Mesajınız atıldı</>' : '<fg=red;options=bold>Mesajınız iletilemedi</>';
        return $message;
        
    }

    public function searchCreateFriends($text){
        $limited  = 1;
        $search = (array) $this->auth->get('search/tweets',array('q'=>$text,"lang"=>"tr","count"=>200));
        sleep(0.5);
        $pattern = "/@|text3|text2|text1/i";
        foreach($search['statuses'] as $tweet){
            if(count(preg_grep($pattern,array($tweet->text,$tweet->user->screen_name,$tweet->user->name,$tweet->user->description))) < 1 &&( $tweet->user->following == false && $tweet->user->followers_count > 75 )){
                if($limited <= 30){
                $createFriends =  (array) $this->auth->post('friendships/create',array('screen_name'=>$tweet->user->screen_name));
                sleep(1.5);
                echo $limited .'-'.$tweet->user->screen_name. "-- takip edilmiştir \n";
                $limited++;
            }
            }
        }
    }

    public function tweetFavorite($text){

        $search = (array)  $this->auth->get('search/tweets',array('q'=>$text,"lang"=>"tr","count"=>75));
        $pattern = "/@|text3|text2|text1/i";
        $count = 10;
        foreach($search['statuses'] as $tweets){
            if(!preg_match($pattern,$tweets->text) && $tweets->user->followers_count > 75){
                $this->auth->post("favorites/create",array("id"=>$tweets->id));
                sleep(1);
                echo $tweets->text. "-- Twit beğenilmiştir \n";
            }
        }
    }
    
}







