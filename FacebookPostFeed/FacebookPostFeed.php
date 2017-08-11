<?php
// no direct access
defined( '_JEXEC' ) or die;

class plgContentFacebookPostFeed extends JPlugin
{

    /**
     * Load the language file on instantiation. Note this is only available in Joomla 3.1 and higher.
     * If you want to support 3.0 series you must override the constructor
     *
     * @var    boolean
     * @since  3.1
     */
    
    
    protected $autoloadLanguage = true;
        
    /**
     * Plugin method with the same name as the event will be called automatically.
     */
    function onContentPrepare($context, &$article, &$params, $limitstart)
    {

        if (preg_match('@{facebookPostFeed}(.*){/facebookPostFeed}@Us', $article->text, $matchPostFeed)) {
            $article->text = preg_replace(sprintf('@(<p>)?{facebookPostFeed}%s{/facebookPostFeed}(</p>)?@s', $matchPostFeed[1]), $this->getRecentPosts($matchPostFeed[1]), $article->text);
        }
        if (preg_match('@{facebookEventFeed}(.*){/facebookEventFeed}@Us', $article->text, $matchEventFeed)) {
            $article->text = preg_replace(sprintf('@(<p>)?{facebookEventFeed}%s{/facebookEventFeed}(</p>)?@s', $matchEventFeed[1]), $this->getRecentEvents($matchEventFeed[1]), $article->text);
        }
        return true;
    }

    /**
     * getRecentEvents returns the HTML of the recent events
     */
    private function getRecentEvents($PageId)
    {
        // Fetches the data
        $RecentEvents = $this->getGraphData(sprintf('/%s/events?fields=place,name,end_time,start_time,description', $PageId));
        $RecentEventsHTML = "";
        $now = new DateTime();
        foreach ($RecentEvents['data'] as $key => $event) {
            if ($key >= $this->params->get('recent_events_max_posts')) {
                break;
            }
            $event_start_time = DateTime::createFromFormat(DateTime::ISO8601, $event['start_time']);
            if (-$now->diff($event_start_time)->format("%r%a") > $this->params->get('recent_events_max_days')) {
                continue;
            }
            $panelClass = 'panel-default';
            $date = $event['start_time'];
            if (array_key_exists('start_time', $event)) {
                $event_start_time = DateTime::createFromFormat(DateTime::ISO8601, $event['start_time']);
                $date = $event_start_time->format('d.m.Y H:i');
                if (array_key_exists('end_time', $event)) {
                    $event_end_time = DateTime::createFromFormat(DateTime::ISO8601, $event['end_time']);
                    $date = $event_start_time->format('d.m.Y H:i').' - '.$event_end_time->format('d.m.Y H:i');
                    $panelClass = $this->isDateBetweenDates($now, $event_start_time, $event_end_time)?'panel-success':$panelClass;
                }
            }
            // $event_end_time = DateTime::createFromFormat(DateTime::ISO8601, $event['end_time']);
            $panelClass = 'panel-default';
            //$date = isset($event_end_time)?$event_start_time->format('d-m-Y H:i').' - '.$event_end_time->format('d-m-Y H:i'):$event_start_time->format('d-m-Y H:i');
            $location_x = "";
            $location_y = "";
            $location_name = "";
            if (array_key_exists('place', $event) && array_key_exists('location', $event['place'])) {
                $location_x = $event['place']['location']['latitude'];
                $location_y = $event['place']['location']['longitude'];
                $location_name = $event['place']['name'];
            }
            $CSS = '
			<style>
				.fbdirect {
					float: right;
					margin-top: 10px;
					margin-right: 10px;
				}
				.datetime-right {
					display: block;
				}
				@media (min-width: 992px) {
					.datetime-right {
						float: right;
					}
				}
			</style>';
            $template = '<div class="panel {panelClass}" style="margin-top: 5px;">
				<div class="panel-heading">
				<h4 class="panel-title">
					<a data-toggle="collapse" data-parent="#accordion" href="#collapse{Id}">{eventName}</a>
					<strong class="datetime-right">{date}</strong>
				</h4>
				</div>
				<div id="collapse{Id}" class="panel-collapse collapse">
					<a href="https://www.facebook.com/events/{eventId}" class="fbdirect" target="_blank"><i class="btn btn-default btn-small fa fa-calendar"> Zum Event</i></a>
					<a href="https://www.google.de/maps/search/{locationX}+{locationY}" title="{locationName}" target="_blank" class="fbdirect"><i class="btn btn-danger btn-sm fa fa-map-marker"></i></a>
					<div class="panel-body">{eventDescription}</div>
				</div>
				</div>
			';
            $RecentEventsHTML .= $this->templateReplacement(array(
                'panelClass' => $panelClass,
                'Id' => $key,
                'eventName' => $event['name'],
                'date' => $date,
                'eventId' => $event['id'],
                'locationX' => $location_x,
                'locationY' => $location_y,
                'locationName' => htmlspecialchars($location_name),
                'eventDescription' => array_key_exists('description', $event)?nl2br($event['description']):""
            ), $template);
        }
        return $this->templateReplacement(array(
                            'CSS' => $CSS,
                            'eventsHeading' => $this->params->get('recent_events_heading'),
                            'eventsDescription' => $this->params->get('recent_events_description'),
                            'rawHTML' => $RecentEventsHTML,
                             ), '{CSS}<div class="container" style="width: 100%;">
							 		<div class="row">
										<h3>{eventsHeading}</h3>
										<p>{eventsDescription}</p>
									</div>
									<div class="row"><div class="panel-group" id="accordion">{rawHTML}</div>
									</div>
								</div>');
    }

    /**
     * getRecentPosts returns the HTML of the recent posts
     */
    private function getRecentPosts($PageId)
    {
        // Fetches the data
        $RecentPosts = $this->getGraphData(sprintf('/%s/posts?fields=message,link,likes,full_picture', $PageId));
        $RecentPostsHTML = "";
        foreach ($RecentPosts['data'] as $key => $post) {
            if ($key >= $this->params->get('recent_posts_max_posts')) {
                break;
            }
            $image = "";
            // Add the image if a picture exists
            if (array_key_exists('full_picture', $post)) {
                $template = '<img src="{url}" alt="{message}" style="width: 100%;">';
                $image = $this->templateReplacement(array(
                    'url' => $post['full_picture'],
                    'message' => array_key_exists('message', $post)?htmlspecialchars($post['message']):""
                ), $template);
            }
            // Check if the message is too long => short and add  "..." at the end
            $message = array_key_exists('message', $post)?$post['message']:"";
            if (strlen($message) > 195) {
                $message = preg_replace('/\s+?(\S+)?$/', '', substr($post['message'], 0, 201))."...";
            }
            // Thumbnail template
            $template = '
			<div class="col-xs-12 col-md-4 mxs-recent-facebook-post">
				<div class="thumbnail">
					{image}
					<div class="caption"><p>{message}</p>
						<p>
							<a href="{link}" class="btn btn-default" role="button">Zum Post</a>
							<a href="{link}" class="btn btn-primary" role="button">
								<i class="fa fa-thumbs-o-up" aria-hidden="true"></i>&nbsp;{likes}
							</a>
						</p>
					</div>
				</div>
			</div>';
            // Builds the PostEntry
            $RecentPostsHTML .= $this->templateReplacement(array(
                'image' => $image,
                'message' => $message,
                'link' => array_key_exists('link', $post)?$post['link']:"#",
                'likes' => array_key_exists('likes', $post)?count($post['likes']):0
            ), $template);
        }
        // CSS
        $CSS = '<style>
			@media (min-width: 992px) {
				.mxs-recent-facebook-post {
					height: 420px;
				}
			}
			</style>';
        $template = '{CSS}<div class="container" style="width: 100%;">
			<div class="row">
				<h3>{heading}</h3>
				<p>{description}</p>
			</div>
			<div class="row mxs-recent-facebook-post-body">{posts}</div></div>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.slim.min.js"></script>
			<script>
            $(window).on("load", function () {
                var minHeight = 0,
                    items = el = document.getElementsByClassName("mxs-recent-facebook-post"),
                    currentStore = [];
                for (var i = 0; i < el.length; i++) {
                    minHeight = minHeight < el[i].scrollHeight ? el[i].scrollHeight : minHeight;
                    currentStore.push(items[i]);
                    if ((i + 1) % 3 === 0 && i !== 0) {
                        for (var j = 0; j < currentStore.length; j++) {
                            currentStore[j].style.minHeight = minHeight.toString() + "px";
                        }
                        currentStore = [];
                        maxHeight = 0;
                    }
                }
            });
            </script>';
        return $this->templateReplacement(array(
            'CSS' => $CSS,
            'heading' => $this->params->get('recent_posts_heading'),
            'description' => $this->params->get('recent_posts_description'),
            'posts' => $RecentPostsHTML
        ), $template);
    }

    /**
     * getGraphData is a helper function for getting the facebook graph data
     */
    private function getGraphData($path)
    {
            // builds the url
            $ch = curl_init(sprintf("https://graph.facebook.com%s", $path));
            // adds the authentification header
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(sprintf("Authorization: OAuth %s|%s", $this->params->get('app_id'), $this->params->get('app_secret'))));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            // execute the session
            $curl_response = curl_exec($ch);
            // finish off the session
            curl_close($ch);
            // returns the nice json
            return json_decode($curl_response, true);
    }


    /**
     * isDateBetweenDates is a helper function for getting a boolen if a date is between 2 other dates
     */
    private function isDateBetweenDates(DateTime $today_timestamp, DateTime $start_timestamp, DateTime $end_timestamp)
    {
         return (($today_timestamp >= $start_timestamp) && ($today_timestamp <= $end_timestamp));
    }
    
    /**
     * templateReplacement is a helper function to inprove the readablity of the templating code
     */
    private function templateReplacement(array $values, string $template)
    {
        $output = $template;
        foreach ($values as $key => $value) {
            $output = str_replace(sprintf('{%s}', $key), $value, $output);
        }
        return $output;
    }
}
