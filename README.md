# WDS Change Comment Author
Allows admins to update/edit the authors of existing comments in wp-admin. Also adds dropdown next to comment box for selecting an alternate user to comment as.

## Available Filters

* `wds_change_comment_author_select`: Filter the user dropdown select output.
* `wds_change_comment_author_pre_get_users`: Filter the query results before it is retrieved. Allows overriding query. Passing an array value to the filter will short-circuit retrieving the query results, returning the passed value instead. Default: `null`
* `wds_change_comment_author_get_users`: Filter the query results after they are retrieved.
* `wds_change_comment_author_capability`: Filter the [capability level](http://codex.wordpress.org/Roles_and_Capabilities#Capabilities) for being able to edit comment authors. Default: `current_user_can( 'manage_options' )`
