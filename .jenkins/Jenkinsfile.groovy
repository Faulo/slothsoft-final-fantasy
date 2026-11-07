def unstable(def body) {
	catchError(stageResult: 'UNSTABLE', buildResult: 'UNSTABLE', catchInterruptions: false) {
		body()
	}
}

def report(def body) {
    checkout scm

    fileOperations([folderDeleteOperation('.reports'), folderCreateOperation('.reports')])

    body()

    junit(testResults: '.reports/*.xml', allowEmptyResults: true)
}

def runComposerTest(def version, def variant) {
	unstable {
        exec "composer update --no-interaction --no-progress --prefer-${variant}"

        exec "php vendor/bin/phpunit --log-junit .reports/${version}-${variant}.xml"
	}
}

def runLinter() {
	unstable {
		exec "composer update --no-interaction --no-progress"

        execStatus(
            script: "php vendor/bin/phpstan analyse --no-progress --configuration=phpstan.neon.dist --error-format=junit",
            stdout: ".reports/phpstan.xml"
        )
	}
}

pipeline {
	agent none
	options {
		disableConcurrentBuilds()
		disableResume()
		disableRestartFromStage()
		skipDefaultCheckout()
	}
	environment {
		COMPOSER_PROCESS_TIMEOUT = '3600'
	}
	stages {
		stage('Lint') {
			agent {
                label 'docker'
            }
			steps {
                script {
                    report {
                        docker.image("faulo/farah:8.2").inside {
                            runLinter();
                        }
                    }
                }
			}
		}
		stage('Test') {
			steps {
				script {
					def platforms = [ 'linux', 'windows' ]
					def versions = [ "8.2", "8.3", "8.4", "8.5" ]
					def variants = [ 'lowest', 'stable' ]

					def branches = [:]

					for (def p in platforms) {
						def platform = p
						for (def v in versions) {
							def version = v
							for (def a in variants) {
								def variant = a

								def name = "${platform} php-${version} ${variant}"
								def label = "${platform} && docker"
								def workspace = "php-${version}-${variant}"

								branches[name] = {
									stage(name) {
										node(label) {
											ws("${WORKSPACE}/${workspace}") {
                                                report {
                                                    docker.image("faulo/farah:${version}").inside {
                                                        runComposerTest(version, variant)
                                                    }
                                                }
											}
										}
									}
								}
							}
						}
					}

					parallel branches
				}
			}
		}
	}
}