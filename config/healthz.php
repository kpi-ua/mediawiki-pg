<?php
/**
 * Liveness endpoint for load balancer target groups and container health
 * checks. Wikis that disable anonymous read answer 403 on "/", which marks
 * every container unhealthy; this path always answers 200.
 *
 * It deliberately does not touch the database, so a database blip does not
 * cause the orchestrator to recycle every container at once.
 */
header( 'Content-Type: text/plain' );
header( 'Cache-Control: no-store' );
echo "ok\n";
