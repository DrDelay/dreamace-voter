# dreamace-voter - DreamACE Voter v1.0.0
Automates the voting process on DreamACE.
This works for now as DreamACE is not using Captchas on their votes.
Also, this tool doesn't really vote. It just fires requests to `ajax/vote.php`, this is enough to get the reward. The wait-time where it "validates" your vote is just show right now.

Install / Use
------
```
git clone https://github.com/DrDelay/dreamace-voter.git
composer install
./dreamace-voter autovote johnny secr3t 1337
```
You may then register this as a cronjob (every 2 hours in this example):
```
0 */2	* *	*	root	/path/to/dreamace-voter/dreamace-voter autovote johnny secr3t 1337
```

Character ID
------
You can get this by inspecting the character-dropdown / viewing page-source on the homepage.
It is the option-value in the chose_character-select.

License
------
MIT
