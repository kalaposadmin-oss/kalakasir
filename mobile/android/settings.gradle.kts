pluginManagement {
    repositories {
        google()
        mavenCentral()
        gradlePluginPortal()
    }
}

dependencyResolutionManagement {
    repositoriesMode.set(RepositoriesMode.FAIL_ON_PROJECT_REPOS)
    repositories {
        google()
        mavenCentral()
    }
}

rootProject.name = "umkm-offline-android"
include(":app")
include(":core-common")
include(":core-database")
include(":core-network")
include(":core-sync")
include(":feature-pos")
include(":feature-auth")
include(":feature-home")
include(":feature-sync-status")
