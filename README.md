# git-deploy

Pure php deployment script that can pull from Github.

Adding a webhook in your GitHub repository, will make GitHub send a the details of each push (or commit for the changes happening directly in the Web interface or through the GitHub API) to your server.

This script will read the details of the commit and

- download per HTTP the files that have been modified or added,
- remove the local files that have been deleted from the repository.

The deployment path can be tweaked by defining both a repository and a deployment basedir.



# Usage

- setup GitHub POST hooks (cf. <https://help.github.com/articles/post-receive-hooks>) to http://deploy.some.site/github.php
- you will probably want to activate the "Push" and "Status" events.
- get the notification as a normal form and not as a json package.

# Notes

- Strong influence by https://github.com/lkwdwrd/git-deploy

# Todo

- implement the "secret"
- if no curl is defined, use the php based curl
- use a config file to replace the few variables.
        $config = array (
            'my_repo' => // name of the repository
                'secret' => 'secret string',
                'branch' => 'master', // only commits to this branch will be retained
                'repository_base_path' => '' // path to be removed from the filename
                'deployment_base_path' => '' // path to be prefixed to the filename
                'log_file' => 'log.txt' // list of the commits
        );
- clean up the code structure and create a composer manifest.
